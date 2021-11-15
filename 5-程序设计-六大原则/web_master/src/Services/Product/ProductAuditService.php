<?php

namespace App\Services\Product;

use App\Enums\Common\YesNoEnum;
use App\Enums\Product\ProductAuditStatus;
use App\Enums\Product\ProductAuditType;
use App\Enums\Product\ProductStatus;
use App\Helper\SummernoteHtmlEncodeHelper;
use App\Logging\Logger;
use App\Models\Link\CustomerPartnerToProduct;
use App\Models\Product\Category;
use App\Models\Product\Option\Option;
use App\Models\Product\Option\ProductOptionValue;
use App\Models\Product\Option\SellerPrice;
use App\Models\Product\Option\SellerPriceHistory;
use App\Models\Product\Product;
use App\Models\Product\ProductAudit;
use App\Repositories\Product\CategoryRepository;
use App\Repositories\Product\ProductAuditRepository;
use App\Repositories\SellerAsset\SellerAssetRepository;
use Carbon\Carbon;
use DateTime;
use Framework\Exception\Exception;
use ModelAccountWishlist;
use ModelCatalogProductPrice;
use stdClass;

class ProductAuditService
{
    /**
     * 新增商品后，插入审核记录  此方法相对专用，如果需要复用，请检查数据结构是否一致
     *
     * @param int $productId
     * @param array $data
     * @return bool|int  false 或 审核记录的主键
     */
    public function insertProductAudit($productId, $data)
    {
        $categories = (array)$data['product_category'];
        $categoryId = app(CategoryRepository::class)->getLastLowerCategoryId($categories);
        $information = [
            'color_option_id' => $data['color'],
            'material_option_id' => $data['material'],
            'sold_separately' => $data['buyer_flag'] ?? 0,
            'title' => $data['name'],
            'current_price' => $data['price'] ?? 0,
            'display_price' => $data['price_display'] ?? 0,
            'group_id' => $data['product_group_ids'] ?: [],
            'image' => $data['image'],
            'non_sellable_on' => $data['non_sellable_on'] ?? null,
            'upc' => $data['upc'],
            'is_customize' => $data['is_customize'] ?: 0,
            'origin_place_code' => $data['origin_place_code'] ?: '',
            'filler' => $data['filler'] ?: 0,
            'custom_field' => $data['information_custom_field'] ?: [],
        ];
        $is_original_design = $data['original_product'] ?? 0;
        //基础信息
        if ($data['combo_flag'] == 1) {
            $combo = [];
            if (isset($data['combo']) && !empty($data['combo'])) {
                foreach ($data['combo'] as $key => $val) {
                    $combo[] = [
                        'product_id' => $val['product_id'],
                        'quantity' => $val['quantity'],
                    ];
                }
            }
            $productInfo = [
                'type_id' => $data['product_type'],
                'combo' => $combo,
                'no_combo' => [
                    'length' => 0,
                    'width' => 0,
                    'height' => 0,
                    'weight' => 0,
                ],
            ];
        } else {
            $productInfo = [
                'type_id' => $data['product_type'],
                'combo' => [],
                'no_combo' => [
                    'length' => $data['length'],
                    'width' => $data['width'],
                    'height' => $data['height'],
                    'weight' => $data['weight'],
                ],
            ];
        }
        $information['product_type'] = $productInfo;
        $information['associated_product_ids'] = $data['product_associated'] ?: [];

        $product_images = [];
        foreach ($data['product_image'] as $key => $value) {
            $product_images[] = ['url' => $value['image'], 'sort' => $value['sort_order'],];
        }

        //产品描述和退货政策
        $material_package = [
            'product_images' => $product_images,
            'images' => $data['material_images'],
            'files' => $data['material_manuals'],
            'videos' => $data['material_video'],
            'designs' => $data['original_design'], // 原创产品的image
            'certification_documents' => $data['certification_documents'] ?: [],
        ];

        // 组装信息
        $assembleInfo = [
            'assemble_length' => $data['assemble_length'] ?: -1.00,
            'assemble_width' => $data['assemble_width'] ?: -1.00,
            'assemble_height' => $data['assemble_height'] ?: -1.00,
            'assemble_weight' => $data['assemble_weight'] ?: -1.00,
            'custom_field' => $data['dimensions_custom_field'] ?: [],
        ];

        $returnWarranty = $data['return_warranty'];
        $returnWarranty['description'] = $data['description'] ?? '';

        $productAuditId = ProductAudit::query()->insertGetId([
            'product_id' => $productId,
            'customer_id' => customer()->getId(),
            'audit_type' => ProductAuditType::PRODUCT_INFO,
            'category_id' => $categoryId,
            'information' => json_encode($information),
            'description' => json_encode($returnWarranty),
            'material_package' => json_encode($material_package),
            'is_original_design' => $is_original_design,
            'assemble_info' => json_encode($assembleInfo),
        ]);
        if ($productAuditId) {
            Product::query()->where('product_id', $productId)->update(['product_audit_id' => $productAuditId]);
            return $productAuditId;
        }
        return false;
    }

    /**
     * 编辑商品后，插入审核记录  此方法相对专用，如果需要复用，请检查数据结构是否一致
     * 分开写，后面status情况不一样，需要特殊处理数据
     * @param int $productId
     * @param array $data
     * @return array ["notice_type"=>1, "audit_id"=>0]，notice_type 1:只是编辑了资料  2:自动提交审核  3:非单独售卖直接上架 ；audit_id 自动提交审核后的审核记录主键
     */
    public function insertProductAuditAfterEdit($productId, $data)
    {
        $productModel = Product::find($productId);
        $productDetail = $productModel->toArray();
        if (in_array($productDetail['status'], ProductStatus::notSale())) {
            $waitCheckExist = ProductAudit::query()
                ->where('product_id', $productId)
                ->where('status', ProductAuditStatus::PENDING)
                ->where('audit_type', ProductAuditType::PRODUCT_INFO)
                ->where('is_delete', 0)
                ->exists();

            if ($data['buyer_flag'] == YesNoEnum::NO) {
                //当把商品编辑成不可单独售卖时：
                //待上架&已下架：如果有待审核的记录，则删除待审核信息，商品直接上架。如果没有待审核的记录，则只保存信息。不做上架处理。
                if (!$waitCheckExist) {
                    return ['notice_type' => 1, 'audit_id' => 0];
                }

                //有审核记录，但是这个时候商品可以自己直接上架，如果再去审核这条记录，2边可能会不一致，和aojieying沟通后，确认删除已存在的审核记录
                ProductAudit::query()
                    ->where('product_id', $productId)
                    ->where('audit_type', ProductAuditType::PRODUCT_INFO)
                    ->where('status', ProductAuditStatus::PENDING)
                    ->update(['is_delete' => 1]);

                return ['notice_type' => 3, 'audit_id' => 0];

            } else {
                //当把商品编辑成可单独售卖
                //如果有待审核的记录，则删除待审核信息，新插入一条审核记录。走审核逻辑。如果没有待审核的记录，只保存信息。
                if (!$waitCheckExist) {
                    return ['notice_type' => 1, 'audit_id' => 0];
                }
            }
        }
        $is_original_design = $data['original_product'] ?? 0;
        $categories = (array)$data['product_category'];
        $categoryId = app(CategoryRepository::class)->getLastLowerCategoryId($categories);
        $information = [
            'color_option_id' => $data['color'],
            'material_option_id' => $data['material'],
            'sold_separately' => $data['buyer_flag'],
            'title' => $data['name'],
            'current_price' => $productDetail['price'], //编辑时候 商品原价
            'display_price' => $productDetail['price_display'],
            'group_id' => $data['product_group_ids'] ?: [],
            'image' => $data['image'],
            'non_sellable_on' => $data['non_sellable_on'],
            'upc' => $data['upc'] ?: '',
            'is_customize' => $data['is_customize'] ?: 0,
            'origin_place_code' => $data['origin_place_code'] ?: '',
            'filler' => $data['filler'] ?: 0,
            'custom_field' => $data['information_custom_field'] ?: [],
        ];

        $assembleInfo['custom_field'] = $data['dimensions_custom_field'] ?: [];
        $assembleInfo['assemble_length'] = $data['assemble_length'] ?: -1.00;
        $assembleInfo['assemble_width'] = $data['assemble_width'] ?: -1.00;
        $assembleInfo['assemble_height'] = $data['assemble_height'] ?: -1.00;
        $assembleInfo['assemble_weight'] = $data['assemble_weight'] ?: -1.00;

        //把现有商品信息组织到审核表
        if ($productDetail['combo_flag'] == 1) {
            $combo = [];
            foreach ($data['combo'] as $key => $val) {
                $combo[] = [
                    'product_id' => $val['product_id'],
                    'quantity' => $val['quantity'],
                ];
            }
            $productInfo = [
                'type_id' => 2,
                'combo' => $combo,
                'no_combo' => [
                    'length' => 0,
                    'width' => 0,
                    'height' => 0,
                    'weight' => 0,
                ],
            ];
        } else {
            $productInfo = [
                'type_id' => $productDetail['part_flag'] == 1 ? 3 : 1,
                'combo' => [],
                'no_combo' => [
                    'length' => customer()->isUSA() ? $productDetail['length'] : $productDetail['length_cm'],
                    'width' => customer()->isUSA() ? $productDetail['width'] : $productDetail['width_cm'],
                    'height' => customer()->isUSA() ? $productDetail['height'] : $productDetail['height_cm'],
                    'weight' => customer()->isUSA() ? $productDetail['weight'] : $productDetail['weight_kg'],
                ],
            ];
        }
        $information['product_type'] = $productInfo;
        $information['associated_product_ids'] = $data['product_associated'] ?? [];

        $product_images = [];
        foreach ($data['product_image'] as $key => $value) {
            $product_images[] = ['url' => $value['image'], 'sort' => $value['sort_order'],];
        }

        //产品描述和退货政策
        $material_package = [
            'product_images' => $product_images,
            'images' => $data['material_images'] ?? [],
            'files' => $data['material_manuals'] ?? [],
            'videos' => $data['material_video'] ?? [],
            'designs' => $data['original_design'] ?? [], // 原创产品的image
            'certification_documents' => $data['certification_documents'] ?: [],
        ];
        ProductAudit::query()
            ->where('product_id', $productId)
            ->where('audit_type', ProductAuditType::PRODUCT_INFO)
            ->where('status', ProductAuditStatus::PENDING)
            ->update(['is_delete' => 1]);
        $returnWarranty = $data['return_warranty'];
        $returnWarranty['description'] = $data['description'] ?? '';
        $productAuditId = ProductAudit::query()->insertGetId([
            'product_id' => $productId,
            'customer_id' => customer()->getId(),
            'audit_type' => 1,
            'category_id' => $categoryId,
            'information' => json_encode($information),
            'description' => json_encode($returnWarranty),
            'material_package' => json_encode($material_package),
            'is_original_design' => $is_original_design,
            'assemble_info' => json_encode($assembleInfo),
        ]);
        if ($productAuditId) {
            Product::query()->where('product_id', $productId)->update(['product_audit_id' => $productAuditId]);
            return ['notice_type' => 2, 'audit_id' => $productAuditId];
        }
        return ['notice_type' => 2, 'audit_id' => $productAuditId];
    }

    /**
     * 编辑 产品信息的审核记录，$data结构与 编辑产品详情页提交的结构 一致。
     * 1. ProductAuditStatus::PENDING 更新
     * 2. ProductAuditStatus::NOT_APPROVED 编辑提交审核后，生成一条待审核记录，同时原来的审核拒绝记录依然存在，依然可编辑
     * @param int $auditId
     * @param array $data
     * @return array|bool
     */
    public function updateOrInsertInfo($auditId, $data)
    {
        $productId = $data['product_id'];
        $productAudit = ProductAudit::find($auditId);
        if (
            $productAudit
            && !in_array($productAudit->status, [ProductAuditStatus::PENDING, ProductAuditStatus::NOT_APPROVED])
            && $productAudit->audit_type != ProductAuditType::PRODUCT_INFO
        ) {
            return false;
        }

        $information = json_decode($productAudit->information, true);

        $productDetail = Product::find($productId)->toArray();

        $categories = (array)$data['product_category'];
        $categoryId = app(CategoryRepository::class)->getLastLowerCategoryId($categories);

        // 33309新增属性
        $information['is_customize'] = $data['is_customize'] ?: 0;
        $information['upc'] = $data['upc'] ?: '';
        $information['origin_place_code'] = $data['origin_place_code'] ?: '';
        $information['filler'] = $data['filler'] ?: 0;
        $information['custom_field'] = $data['information_custom_field'] ?: [];

        $information['color_option_id'] = $data['color'];
        $information['material_option_id'] = $data['material'];
        $information['sold_separately'] = $data['buyer_flag'];
        $information['title'] = $data['name'];
        $information['group_id'] = $data['product_group_ids'] ?: [];
        $information['image'] = $data['image'];
        $information['non_sellable_on'] = $data['non_sellable_on'];
        $is_original_design = $data['original_product'] ?? 0;

        //把现有商品信息组织到审核表
        if ($productDetail['combo_flag'] == 1) {
            $combo = [];
            foreach ($data['combo'] as $key => $val) {
                $combo[] = [
                    'product_id' => $val['product_id'],
                    'quantity' => $val['quantity'],
                ];
            }
            $productInfo = [
                'type_id' => 2,
                'combo' => $combo,
                'no_combo' => [
                    'length' => 0,
                    'width' => 0,
                    'height' => 0,
                    'weight' => 0,
                ],
            ];
        } else {
            $productInfo = [
                'type_id' => $productDetail['part_flag'] == 1 ? 3 : 1,
                'combo' => [],
                'no_combo' => [
                    'length' => customer()->isUSA() ? $productDetail['length'] : $productDetail['length_cm'],
                    'width' => customer()->isUSA() ? $productDetail['width'] : $productDetail['width_cm'],
                    'height' => customer()->isUSA() ? $productDetail['height'] : $productDetail['height_cm'],
                    'weight' => customer()->isUSA() ? $productDetail['weight'] : $productDetail['weight_kg'],
                ],
            ];
        }
        $information['product_type'] = $productInfo;
        // 关联产品
        $data['product_associated'] = app(ProductOptionService::class)->updateProductAssociate($productId, $data['product_associated'] ?? []);
        $information['associated_product_ids'] = $data['product_associated'];

        $assembleInfo = [
            'assemble_length' => $data['assemble_length'] ?: '',
            'assemble_width' => $data['assemble_width'] ?: '',
            'assemble_height' => $data['assemble_height'] ?: '',
            'assemble_weight' => $data['assemble_weight'] ?: '',
            'custom_field' => $data['dimensions_custom_field'] ?? [],
        ];

        $product_images = [];
        foreach ($data['product_image'] as $key => $value) {
            $product_images[] = ['url' => $value['image'], 'sort' => $value['sort_order'],];
        }

        //产品描述和退货政策
        $material_package = [
            'product_images' => $product_images,
            'images' => $data['material_images'] ?? [],
            'files' => $data['material_manuals'] ?? [],
            'videos' => $data['material_video'] ?? [],
            'designs' => $data['original_design'] ?? [], // 原创产品的image
            'certification_documents' => $data['certification_documents'] ?? [], // 认证图片
        ];
        $returnWarranty = $data['return_warranty'];
        $returnWarranty['description'] = $data['description'] ?? '';

        ProductAudit::query()
            ->where('id', '!=', $auditId)
            ->where('product_id', $productId)
            ->where('audit_type', ProductAuditType::PRODUCT_INFO)
            ->where('status', ProductAuditStatus::PENDING)
            ->update(['is_delete' => 1]);
        switch ($productAudit->status) {
            case ProductAuditStatus::PENDING:
                ProductAudit::query()->where(['id' => $auditId])->update([
                    'status' => ProductAuditStatus::PENDING,
                    'category_id' => $categoryId,
                    'information' => json_encode($information),
                    'description' => json_encode($returnWarranty),
                    'material_package' => json_encode($material_package),
                    'is_original_design' => $is_original_design,
                    'assemble_info' => json_encode($assembleInfo),
                ]);
                break;
            case ProductAuditStatus::NOT_APPROVED:
                $auditId = ProductAudit::query()->insertGetId([
                    'product_id' => $productId,
                    'customer_id' => $productAudit->customer_id,
                    'status' => ProductAuditStatus::PENDING,
                    'create_time' => Carbon::now(),
                    'audit_type' => ProductAuditType::PRODUCT_INFO,
                    'category_id' => $categoryId,
                    'information' => json_encode($information),
                    'description' => json_encode($returnWarranty),
                    'material_package' => json_encode($material_package),
                    'is_original_design' => $is_original_design,
                    'assemble_info' => json_encode($assembleInfo),
                ]);
                break;
        }


        $productAuditInfo = app(ProductAuditRepository::class)->getSellerProductAuditInfo($auditId, $productId);
        $productAuditInfo['audit_id'] = $auditId;
        $productAuditInfo['name'] = SummernoteHtmlEncodeHelper::decode($productAuditInfo['name'], true);
        $productAuditInfo['product_size'] = SummernoteHtmlEncodeHelper::decode($productAuditInfo['product_size'], true);
        $productAuditInfo['description'] = SummernoteHtmlEncodeHelper::decode($productAuditInfo['description']);
        return $productAuditInfo;
    }

    /**
     * 库存订阅价格变动提醒
     * @param SellerPrice $oldSellerPrice
     * @param $displayPriceBefore
     * @param $price
     * @param $effectTime
     */
    private function addPriceCommunication(SellerPrice $oldSellerPrice, $displayPriceBefore, $price, $effectTime)
    {
        if (empty($oldSellerPrice)) {
            return;
        }

        try {
            /** @var ModelAccountWishlist $accountWishlist */
            $accountWishlist = load()->model('account/wishlist');
            if ($oldSellerPrice->status != 1 || $oldSellerPrice->new_price != $price || $oldSellerPrice->effect_time != $effectTime) {
                $displayPriceAfter = $accountWishlist->getWishListPriceArray($oldSellerPrice->product_id, $price);
                $accountWishlist->addPriceCommunication($displayPriceBefore, $displayPriceAfter, $oldSellerPrice->product_id, $effectTime ? new DateTime($effectTime) : null);
            }
        } catch (\Exception $e) {
            Logger::modifyPrices('price addPriceCommunication' . $e->getMessage(), 'info');
        }
    }

    /**
     * 修改价格
     * @param int $productId
     * @param int $sellerId
     * @param int $countryId
     * @param float $price
     * @param string $effectTime
     * @param null $displayPrice
     * @return int|void
     * @throws Exception
     */
    public function modifyProductPrice(int $productId, int $sellerId, int $countryId, float $price, string $effectTime, $displayPrice = null)
    {
        //获取修改价格前的该产品所有订阅buyer展示的价格
        /** @var ModelAccountWishlist $accountWishlist */
        $accountWishlist = load()->model('account/wishlist');
        $displayPriceBefore = $accountWishlist->getWishListPriceArray($productId);

        $product = Product::query()->with(['customerPartnerToProduct', 'description', 'ext'])->find($productId);
        if (!$product->exists() || $product->customerPartnerToProduct->customer_id != $sellerId) {
            throw new Exception('Not Found', 404);
        }

        if (!is_null($displayPrice)) {
            $product->price_display = intval($displayPrice);
        }

        $product->date_modified = Carbon::now()->toDateTimeString();

        // 价格未变动
        if ($product->price == $price) {
            /** @var SellerPrice $sellerPrice */
            $sellerPrice = SellerPrice::query()->where('product_id', $product->product_id)->first();
            try {
                dbTransaction(function () use ($product, $price, $sellerPrice) {
                    // 取消存在的价格审核
                    $this->cancelProductPriceAudit($product->product_id);
                    if ($sellerPrice && $sellerPrice->status == 1) {
                        // 重置即将生效价格
                        $this->resetSellerPrice($sellerPrice);
                    }
                    $product->save();
                    // 影响产品首次审核的价格
                    if ($product->status == ProductStatus::WAIT_SALE) {
                        $this->updateFirstProductAuditInformation($product);
                    }
                });
            } catch (\Throwable $e) {
                throw new Exception('', 500);
            }
            return;
        }

        switch ($product->status) {
            // 待上架随意修改价格
            case ProductStatus::WAIT_SALE:
                // 直接修改产品价格
                $this->directSaveProductPrice($product, $sellerId, $price);
                // 影响产品首次审核的价格
                $this->updateFirstProductAuditInformation($product);
                return;
            // 已上/下架(涨价)的修改需有24小时价格保护，幅度超过70%需提交审核
            case ProductStatus::OFF_SALE:
            case ProductStatus::ON_SALE:
                if ($product->price == 0 || ((abs($product->price - $price)) / $product->price > 0.7)) {
                    // 触发价格审核
                    $generateProductAudit = $this->generateProductAuditByProductId($product, $countryId);
                    try {
                        dbTransaction(function () use ($product, $sellerId, $price, $effectTime, $displayPrice, $generateProductAudit) {
                            /** @var ProductAudit $priceAudit */
                            $priceAudit = ProductAudit::query()->where('is_delete', YesNoEnum::NO)->where('status', ProductAuditStatus::PENDING)->find($product->price_audit_id);
                            /** @var SellerPrice $sellerPrice */
                            $sellerPrice = SellerPrice::query()->where('product_id', $product->product_id)->where('status', 1)->first();
                            if ($priceAudit && $priceAudit->price == $price && $priceAudit->price_effect_time == $effectTime) {
                                $priceAudit->display_price = !is_null($displayPrice) ? $displayPrice : $product->price_display;
                                $priceAudit->save();
                                if ($sellerPrice) {
                                    // 重置即将生效价格
                                    $this->resetSellerPrice($sellerPrice);
                                }
                            } elseif ($sellerPrice && $sellerPrice->new_price == $price && $sellerPrice->effect_time == $effectTime) {
                                $this->delProductPriceAudit($product->product_id);
                            } else {
                                $this->delProductPriceAudit($product->product_id);

                                list($categoryId, $information, $description, $materialPackage, $assembleInfo) = $generateProductAudit;
                                $productAuditId = ProductAudit::query()->insertGetId([
                                    'product_id' => $product->product_id,
                                    'customer_id' => $sellerId,
                                    'create_time' => Carbon::now()->toDateTimeString(),
                                    'audit_type' => ProductAuditType::PRODUCT_PRICE,
                                    'price' => $price,
                                    'display_price' => !is_null($displayPrice) ? $displayPrice : $product->price_display,
                                    'price_effect_time' => $effectTime ?: null,
                                    'category_id' => $categoryId,
                                    'information' => json_encode($information),
                                    'description' => json_encode($description),
                                    'material_package' => json_encode($materialPackage),
                                    'is_original_design' => !empty($materialPackage['designs']) ? 1 : 0,
                                    'assemble_info' => json_encode($assembleInfo),
                                ]);

                                $product->price_audit_id = $productAuditId;

                                if ($sellerPrice) {
                                    $this->resetSellerPrice($sellerPrice);
                                }
                            }

                            $product->save();
                        });
                        return 2;
                    } catch (\Throwable $e) {
                        throw new Exception('', 500);
                    }
                }

                // 直接修改 有价格保护
                /** @var SellerPrice $sellerPrice */
                $oldSellerPrice = SellerPrice::query()->where('product_id', $product->product_id)->first();
                try {
                    $effectTime = dbTransaction(function () use ($product, $price, $effectTime, $sellerId, $oldSellerPrice) {
                        // 涨价  降价设置生效时间的且大于当前时间的
                        if ($price > $product->price || ($price < $product->price && !empty($effectTime)) && strtotime($effectTime) > time()) {
                            if (empty($effectTime)) {
                                // 存在分秒的需要加1小时
                                $parseEffectDate = Carbon::now()->addDay();
                                $minute = $parseEffectDate->minute;
                                $second = $parseEffectDate->second;
                                if ($minute != 0 || $second != 0) {
                                    $parseEffectDate = $parseEffectDate->addHour();
                                }
                                $effectTime = $parseEffectDate->format('Y-m-d H:00:00');
                            }
                            SellerPrice::query()->updateOrInsert(['product_id' => $product->product_id], [
                                'new_price' => $price,
                                'effect_time' => $effectTime,
                                'status' => 1,
                            ]);
                            $product->save();
                        } else {
                            $this->directSaveProductPrice($product, $sellerId, $price);
                            // 影响产品首次审核的价格
                            $this->updateFirstProductAuditInformation($product);
                            // 设置降价率
                            /** @var ModelCatalogProductPrice $modelCatalogProductPrice */
                            $modelCatalogProductPrice = load()->model('catalog/productPrice');
                            $modelCatalogProductPrice->originalPriceChangeRateTwoWeek($product->product_id);

                            if (!empty($oldSellerPrice) && $oldSellerPrice instanceof SellerPrice) {
                                $this->resetSellerPrice($oldSellerPrice);
                            }
                        }

                        $this->delProductPriceAudit($product->product_id);

                        return $effectTime;

                    });
                } catch (\Throwable $e) {
                    throw new Exception('', 500);
                }

                if ($product->status != ProductStatus::ON_SALE) {
                    return;
                }

                //库存订阅价格变动提醒
                if (!empty($effectTime)) {
                    $effectTime = date('Y-m-d H:00:00', strtotime(analyze_time_string($effectTime)));
                }
                if (empty($oldSellerPrice) || !$oldSellerPrice instanceof SellerPrice) {
                    $oldSellerPrice = new SellerPrice();
                    $oldSellerPrice->product_id = $productId;
                }
                $this->addPriceCommunication($oldSellerPrice, $displayPriceBefore, $price, $effectTime);

                return;
        }
    }

    /**
     * @param SellerPrice $sellerPrice
     */
    private function resetSellerPrice(SellerPrice $sellerPrice)
    {
        $sellerPrice->new_price = null;
        $sellerPrice->effect_time = null;
        $sellerPrice->status = 0;
        $sellerPrice->save();
    }

    /**
     * @param int $productId
     */
    private function delProductPriceAudit(int $productId)
    {
        ProductAudit::query()
            ->where('product_id', $productId)
            ->where('audit_type', ProductAuditType::PRODUCT_PRICE)
            ->where('status', ProductAuditStatus::PENDING)
            ->update(['is_delete' => YesNoEnum::YES]);
    }

    /**
     * @param int $productId
     */
    private function cancelProductPriceAudit(int $productId)
    {
        ProductAudit::query()
            ->where('product_id', $productId)
            ->where('audit_type', ProductAuditType::PRODUCT_PRICE)
            ->where('status', ProductAuditStatus::PENDING)
            ->update(['status' => ProductAuditStatus::CANCEL]);
    }

    /**
     * 直接修改价格
     * @param Product $product
     * @param int $sellerId
     * @param float $price
     */
    private function directSaveProductPrice(Product $product, int $sellerId, float $price)
    {
        $product->price = $price;
        $product->save();

        CustomerPartnerToProduct::query()
            ->where('customer_id', $sellerId)
            ->where('product_id', $product->product_id)
            ->update(['price' => $price, 'seller_price' => $price]);

        // 增加产品价格记录
        SellerPriceHistory::query()->insert([
            'product_id' => $product->product_id,
            'price' => $price,
            'add_date' => Carbon::now(),
            'status' => 1,
        ]);
        try {
            $response = app(ProductService::class)->packedZip($sellerId, $product->product_id);
            $result = $response->toArray();
            if (!$result['code'] == 200) {
                Logger::packZip('降价打包请求失败:' . $product->product_id . $result['message'], 'error');
            }
        } catch (\Exception $e) {
            Logger::packZip('降价打包请求失败:' . $product->product_id . $e->getMessage(), 'error');
        }
    }

    /**
     * 保存产品信息上架审核
     * @param int $productId
     * @param int $sellerId
     * @param int $countryId
     * @return bool|string
     * @throws Exception
     */
    public function saveProductInfoAudit(int $productId, int $sellerId, int $countryId)
    {
        $product = Product::query()->with(['customerPartnerToProduct', 'description', 'productOptionValues', 'ext'])->find($productId);
        if ($product->customerPartnerToProduct->customer_id != $sellerId) {
            throw new Exception('Not Found', 404);
        }

        if ($product->status == ProductStatus::ON_SALE) {
            throw new Exception('', 400);
        }

        // 需判断上架信息是否完整，现逻辑可根据产品主图和颜色材质属性判断
        if (empty($product->image) && $product->buyer_flag == YesNoEnum::YES) {
            throw new Exception('', 403);
        }
        $colorOption = $product->productOptionValues->where('option_id', Option::COLOR_OPTION_ID)->first();
        $materialOption = $product->productOptionValues->where('option_id', Option::MATERIAL_OPTION_ID)->first();
        if (empty($colorOption) || empty($materialOption) || empty($product->description->return_warranty)) {
            throw new Exception('', 403);
        }
        // #33303 新字段判断信息完整
        if (empty($product->ext) || empty($product->ext->assemble_length) || empty($product->ext->assemble_height) || empty($product->ext->assemble_width) || empty($product->ext->assemble_weight)) {
            throw new Exception('', 403);
        }

        //onsite类型的seller没有运费 禁止上架
        if (customer()->isGigaOnsiteSeller() && (empty($product->freight) || $product->freight == '0.00')) {
            $code = 406;
            $currentTag = $product->tags->pluck('tag_id')->toArray() ?? [];
            if (in_array(configDB('tag_id_oversize'), $currentTag)) {
                $code = 407;
            }
            throw new Exception('', $code);
        }

        //不可单独售卖商品，上架无需审核
        if ($product->buyer_flag == YesNoEnum::NO) {
            $product->status = ProductStatus::ON_SALE;
            $product->save();

            return 'success'; //做个区分
        }

        [$categoryId, $information, $description, $materialPackage, $assembleInfo] = $this->generateProductAuditByProductId($product, $countryId);

        try {
            dbTransaction(function () use ($product, $sellerId, $categoryId, $information, $description, $materialPackage, $assembleInfo) {
                ProductAudit::query()
                    ->where('product_id', $product->product_id)
                    ->where('audit_type', ProductAuditType::PRODUCT_INFO)
                    ->where('status', ProductAuditStatus::PENDING)
                    ->update(['is_delete' => YesNoEnum::YES]);

                $productAuditId = ProductAudit::query()->insertGetId([
                    'product_id' => $product->product_id,
                    'customer_id' => $sellerId,
                    'create_time' => Carbon::now()->toDateTimeString(),
                    'audit_type' => ProductAuditType::PRODUCT_INFO,
                    'category_id' => $categoryId,
                    'information' => json_encode($information),
                    'description' => json_encode($description),
                    'material_package' => json_encode($materialPackage),
                    'is_original_design' => $materialPackage['designs'] ? 1 : 0,
                    'assemble_info' => json_encode($assembleInfo),
                ]);

                $product->product_audit_id = $productAuditId;
                $product->save();
            });
        } catch (\Throwable $e) {
            throw new Exception('', 500);
        }

        return true;
    }

    /**
     * 生成产品审核数据
     * @param Product $product
     * @param int $countryId
     * @return array
     */
    private function generateProductAuditByProductId(Product $product, int $countryId)
    {
        $isUSA = $countryId == AMERICAN_COUNTRY_ID;

        /** @var Category $category */
        $category = $product->categories->first();
        $categoryId = $category ? $category->category_id : 0;

        /** @var ProductOptionValue $colorOption */
        $colorOption = $product->productOptionValues->where('option_id', Option::COLOR_OPTION_ID)->first();
        /** @var ProductOptionValue $materialOption */
        $materialOption = $product->productOptionValues->where('option_id', Option::MATERIAL_OPTION_ID)->first();
        $information['color_option_id'] = $colorOption ? $colorOption->option_value_id : 0;
        $information['material_option_id'] = $materialOption ? $materialOption->option_value_id : 0;
        $information['sold_separately'] = $product->buyer_flag;
        $information['title'] = $product->description->name;
        $information['current_price'] = $product->price;
        $information['display_price'] = $product->price_display;
        $information['non_sellable_on'] = $product->non_sellable_on;
        $information['group_id'] = $product->customerPartnerProductGroupLinks()->where('status', YesNoEnum::YES)->get()->pluck('product_group_id')->unique()->toArray();
        $information['image'] = $product->image;
        if ($product->combo_flag) {
            $productType = [
                'type_id' => 2,
                'combo' => $product->combos()->selectRaw('set_product_id as product_id, qty as quantity')->get()->toArray(),
                'no_combo' => [
                    'length' => 0,
                    'width' => 0,
                    'height' => 0,
                    'weight' => 0,
                ],
            ];
        } elseif ($product->part_flag) {
            $productType = [
                'type_id' => 3,
                'combo' => [],
                'no_combo' => [
                    'length' => $isUSA ? $product->length : $product->length_cm,
                    'width' => $isUSA ? $product->width : $product->width_cm,
                    'height' => $isUSA ? $product->height : $product->height_cm,
                    'weight' => $isUSA ? $product->weight : $product->weight_kg,
                ],
            ];
        } else {
            $productType = [
                'type_id' => 1,
                'combo' => [],
                'no_combo' => [
                    'length' => $isUSA ? $product->length : $product->length_cm,
                    'width' => $isUSA ? $product->width : $product->width_cm,
                    'height' => $isUSA ? $product->height : $product->height_cm,
                    'weight' => $isUSA ? $product->weight : $product->weight_kg,
                ],
            ];
        }
        $information['product_type'] = $productType;
        $information['upc'] = $product->upc;
        $information['associated_product_ids'] = $product->associatesProducts()->where('associate_product_id', '!=', $product->product_id)->pluck('associate_product_id')->toArray();

        $assembleInfo['custom_field'] = $product->dimensionCustomFields()->select(['name', 'value', 'sort'])->get();
        if ($product->ext) {
            $information['is_customize'] = $product->ext->is_customize;
            $information['origin_place_code'] = $product->ext->origin_place_code;
            $information['filler'] = $product->ext->filler;

            $assembleInfo['assemble_length'] = $product->ext->assemble_length;
            $assembleInfo['assemble_width'] = $product->ext->assemble_width;
            $assembleInfo['assemble_height'] = $product->ext->assemble_height;
            $assembleInfo['assemble_weight'] = $product->ext->assemble_weight;
        }
        $information['custom_field'] = $product->informationCustomFields()->select(['name', 'value', 'sort'])->get();

        $description = [
            'return_warranty' => !empty($product->description->return_warranty) ? json_decode($product->description->return_warranty) : new StdClass(),
            'description' => $product->description->description,
            'return_warranty_text' => $product->description->return_warranty_text,
        ];

        $materialPackage['product_images'] = $product->images()->selectRaw('image as url, sort_order as sort')->get()->toArray();
        $materialPackage['images'] = $product->packageImages()->selectRaw('origin_image_name as name, file_upload_id as file_id, image as url, product_package_image_id as m_id')->get()->toArray();
        $materialPackage['files'] = $product->packageFiles()->selectRaw('origin_file_name as name, file_upload_id as file_id, file as url, product_package_file_id as m_id')->get()->toArray();
        $materialPackage['videos'] = $product->packageVideos()->selectRaw('origin_video_name as name, file_upload_id as file_id, video as url, product_package_video_id as m_id')->get()->toArray();
        $materialPackage['designs'] = $product->packageOriginalDesignImages()->selectRaw('origin_image_name as name, file_upload_id as file_id, image as url, product_package_original_design_image_id as m_id')->get()->toArray();
        $materialPackage['certification_documents'] = $product->certificationDocuments()->selectRaw('url, name, type_id')->get()->toArray();

        return [$categoryId, $information, $description, $materialPackage, $assembleInfo];
    }

    /**
     * Seller 取消“产品审核”的申请
     * @param $auditId
     * @param int $sellerId
     * @return void
     * @throws Exception
     */
    public function cancelProductAudit($auditId, $sellerId)
    {
        /** @var ProductAudit $productAudit */
        $productAudit = app(ProductAuditRepository::class)->getProductAuditByIdAndCustomerId(intval($auditId), intval($sellerId));
        if (empty($productAudit)) {
            throw new Exception('', 404);
        }
        if ($productAudit->is_delete == YesNoEnum::YES) {
            throw new Exception('', 400);
        }
        if ($productAudit->status != ProductAuditStatus::PENDING) {
            throw new Exception('', 403);
        }

        $productAudit->status = ProductAuditStatus::CANCEL;
        $productAudit->update_time = Carbon::now();
        $productAudit->save();
    }

    /**
     * Seller 软删除“产品审核”的申请
     * @param array $ids product_audit主键
     * @param int $sellerId
     * @return array
     */
    public function deleteProductAudit($ids = [], $sellerId)
    {
        $rows = db('oc_product_audit')->where([
            ['customer_id', '=', $sellerId],
            ['is_delete', '=', 0],
        ])
            ->whereIn('id', $ids)
            ->update(['is_delete' => 1, 'update_time' => Carbon::now()]);
        if ($rows) {
            return ['code' => 0, 'msg' => 'Success'];
        }
        return ['code' => 1, 'msg' => ''];
    }

    /**
     * 更新首次产品审核的信息
     * @param Product $product
     */
    private function updateFirstProductAuditInformation(Product $product)
    {
        $productInfoAudit = ProductAudit::query()->where('is_delete', YesNoEnum::NO)->where('status', ProductAuditStatus::PENDING)->find($product->product_audit_id);
        if ($productInfoAudit) {
            $formatInformation = $productInfoAudit->format_information;
            $formatInformation['current_price'] = $product->price;
            $formatInformation['display_price'] = $product->price_display;
            $productInfoAudit->information = json_encode($formatInformation);
            $productInfoAudit->save();
        }
    }
}
