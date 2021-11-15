<?php

namespace App\Services\Product;

use App\Components\Storage\StorageCloud;
use App\Enums\Customer\CustomerAccountingType;
use App\Enums\Product\ProductCustomFieldType;
use App\Enums\Product\ProductStatus;
use App\Helper\ProductHelper;
use App\Logging\Logger;
use App\Models\CustomerPartner\CustomerPartnerToCustomer;
use App\Models\Link\ProductToTag;
use App\Models\Log\ProductLtlLog;
use App\Models\Product\CategoryDescription;
use App\Models\Product\Option\ProductImage;
use App\Models\Product\Option\SellerPriceHistory;
use App\Models\Product\Product;
use App\Models\Product\ProductCertificationDocument;
use App\Models\Product\ProductCustomField;
use App\Models\Product\ProductExts;
use App\Models\Product\ProductFee;
use App\Models\Product\ProductSetInfo;
use App\Repositories\File\CustomerFileManageRepository;
use App\Repositories\Product\ProductRepository;
use Carbon\Carbon;
use Psr\SimpleCache\InvalidArgumentException;
use Symfony\Component\HttpClient\HttpClient;

class ProductService
{
    const DEFAULT_BLANK_IMAGE = 'default/blank.png';

    /**
     * 设置产品ltl属性
     * @param array $products
     * @param int $customerId
     * @param string $operator
     * @param int $countryId
     * @return bool
     */
    public function setProductsLtlTag(array $products, int $customerId, string $operator, int $countryId)
    {
        $idProductMap = [];
        $productToTags = [];
        $productToTagLogs = [];
        foreach ($products as $product) {
            /** @var Product $product */
            // 删除多余数据
            unset($product->girt_longest_side);
            unset($product->remind_volumes);
            if (ProductToTag::query()->where('product_id', $product->product_id)->where('tag_id', 1)->exists()) {
                continue;
            }
            $idProductMap[$product->product_id] = $product;

            $productToTags[$product->product_id] = [
                'product_id' => $product->product_id,
                'tag_id' => 1,
                'is_sync_tag' => 0,
                'create_user_name' => "seller_id={$customerId}",
                'create_time' => Carbon::now(),
                'program_code' => 'B2B_Seller',
            ];

            // combo也需设置为ltl
            if ($parentProducts = $product->parentProducts) {
                foreach ($parentProducts as $parentProduct) {
                    /** @var ProductSetInfo $parentProduct */
                    $idProductMap[$parentProduct->product_id] = $parentProduct->product;
                    if (ProductToTag::query()->where('product_id', $parentProduct->product_id)->where('tag_id', 1)->exists()) {
                        continue;
                    }
                    $productToTags[$parentProduct->product_id] = [
                        'product_id' => $parentProduct->product_id,
                        'tag_id' => 1,
                        'is_sync_tag' => 0,
                        'create_user_name' => "seller_id={$customerId}",
                        'create_time' => Carbon::now(),
                        'program_code' => 'B2B_Seller',
                    ];
                }
            }

            $productToTagLogs[] = [
                'product_id' => $product->product_id,
                'ltl_type' => ProductLtlLog::LTL_TYPE_SET,
                'length' => $product->length,
                'width' => $product->width,
                'height' => $product->height,
                'weight' => $product->weight,
                'remark' => '',
                'operator' => $operator,
                'create_time' => Carbon::now(),
            ];
        }

        if (!empty($productToTags)) {
            ProductToTag::query()->insert($productToTags);
            // 处理美国产品信息同步
            if ($countryId == AMERICAN_COUNTRY_ID && !customer()->isInnerAccount()) {
                try {
                    ProductHelper::sendSyncProductsRequest(array_column($productToTagLogs, 'product_id'));
                } catch (\Throwable $e) {
                    Logger::syncProducts($e->getMessage());
                }
            }
        }

        // 记录ltl日志
        if (!empty($productToTagLogs)) {
            ProductLtlLog::query()->insert($productToTagLogs);
        }

        // 更新运费
        if (!empty($idProductMap)) {
            $this->updateProductsFreight($idProductMap);
        }

        return true;
    }

    /**
     * 取消产品ltl属性
     * @param array $products
     * @param string $operator
     * @param int $countryId
     * @return bool
     * @throws \Exception
     */
    public function cancelProductsLtlTag(array $products, string $operator, int $countryId)
    {
        $idProductMap = [];
        $cancelProductIds = [];
        $productToTagLogs = [];
        $comboIdProductMap = [];
        foreach ($products as $product) {
            /** @var Product $product */
            // 删除多余数据
            unset($product->girt_longest_side);
            unset($product->remind_volumes);
            if (!ProductToTag::query()->where('product_id', $product->product_id)->where('tag_id', 1)->exists()) {
                continue;
            }
            $idProductMap[$product->product_id] = $product;

            $cancelProductIds[] = $product->product_id;

            if ($parentProducts = $product->parentProducts) {
                foreach ($parentProducts as $parentProduct) {
                    /** @var ProductSetInfo $parentProduct */
                    $comboIdProductMap[$parentProduct->product_id] = $parentProduct->product;
                }
            }

            $productToTagLogs[] = [
                'product_id' => $product->product_id,
                'ltl_type' => ProductLtlLog::LTL_TYPE_CANCEL,
                'length' => $product->length,
                'width' => $product->width,
                'height' => $product->height,
                'weight' => $product->weight,
                'remark' => '',
                'operator' => $operator,
                'create_time' => Carbon::now(),
            ];
        }

        if (!empty($cancelProductIds)) {
            ProductToTag::query()->where('tag_id', 1)->whereIn('product_id', $cancelProductIds)->delete();

            // combo也需取消ltl
            $comboCancelProductIds = [];
            foreach ($comboIdProductMap as $comboProductId => $comboProduct) {
                $comboSetProductIds = ProductSetInfo::query()->where('product_id', $comboProductId)->pluck('set_product_id')->toArray();
                if (empty($comboSetProductIds) || ProductToTag::query()->whereIn('product_id', $comboSetProductIds)->where('tag_id', 1)->exists()) {
                    continue;
                }
                $comboCancelProductIds[] = $comboProductId;
                $cancelProductIds[] = $comboProductId;
                $idProductMap[$comboProductId] = $comboProduct;

                $productToTagLogs[] = [
                    'product_id' => $comboProductId,
                    'ltl_type' => ProductLtlLog::LTL_TYPE_CANCEL,
                    'length' => $comboProduct->length,
                    'width' => $comboProduct->width,
                    'height' => $comboProduct->height,
                    'weight' => $comboProduct->weight,
                    'remark' => '子产品取消ltl, combo也需取消',
                    'operator' => $operator,
                    'create_time' => Carbon::now(),
                ];
            }
            if (!empty($comboCancelProductIds)) {
                ProductToTag::query()->where('tag_id', 1)->whereIn('product_id', $comboCancelProductIds)->delete();
            }

            // 处理美国产品信息同步
            if ($countryId == AMERICAN_COUNTRY_ID && !customer()->isInnerAccount()) {
                try {
                    ProductHelper::sendSyncProductsRequest($cancelProductIds);
                } catch (\Throwable $e) {
                    Logger::syncProducts($e->getMessage());
                }
            }
        }

        // 记录ltl日志
        if (!empty($productToTagLogs)) {
            ProductLtlLog::query()->insert($productToTagLogs);
        }

        // 更新运费
        if (!empty($idProductMap)) {
            $this->updateProductsFreight($idProductMap);
        }

        return true;
    }

    /**
     * 更新运费 $idProductMap中可能是Product对象 也可能是productId
     * @param array $idProductMap
     */
    public function updateProductsFreight(array $idProductMap)
    {
        try {
            $freights = ProductHelper::sendProductsFreightRequest(array_keys($idProductMap));
            if (empty($freights)) {
                Logger::importProducts('批量获取运费返回为空');
            }
        } catch (\Throwable $e) {
            Logger::importProducts('批量获取运费失败:' . $e->getMessage(), 'error');
            $freights = [];
        }

        $productIdFreights = array_column($freights, null, 'productId');

        $handledProductIds = [];
        $insertProductFees = [];
        foreach ($idProductMap as $productId => $product) {
            /** @var Product $product */
            if (!isset($productIdFreights[$productId]) || in_array($productId, $handledProductIds)) {
                continue;
            }

            $freight = $productIdFreights[$productId];

            $productFreight = $freight['accountFreight'] ?? 0;
            $productPackageFee = $freight['packageFee'] ?? 0;
            $productPeakSeasonTotalSurcharge = $freight['peakSeasonTotalSurcharge'] ?? 0;
            $dangerFee = $freight['dangerFee'] ?? 0;

            if ($product instanceof Product) {
                $product->freight = $productFreight;
                $product->package_fee = $productPackageFee;
                $product->peak_season_surcharge = $productPeakSeasonTotalSurcharge;
                $product->danger_fee = $dangerFee;
                $product->save();
            } else {
                $updateData = [
                    'freight' => $productFreight,
                    'package_fee' => $productPackageFee,
                    'peak_season_surcharge' => $productPeakSeasonTotalSurcharge,
                    'danger_fee' => $dangerFee,
                ];
                Product::query()->where('product_id', $productId)->update($updateData);
            }

            if (isset($freight['dropShipPackageFee'])) {
                $productDropShipPackageFee = ProductFee::query()->where('product_id', $productId)->where('type', 2)->first();
                if ($productDropShipPackageFee) {
                    $productDropShipPackageFee->fee = $freight['dropShipPackageFee'];
                    $productDropShipPackageFee->update_time = Carbon::now();
                    $productDropShipPackageFee->save();
                } else {
                    $insertProductFees[] = [
                        'product_id' => $productId,
                        'type' => 2,
                        'fee' => $freight['dropShipPackageFee'],
                        'create_time' => Carbon::now(),
                        'update_time' => Carbon::now(),
                    ];
                }
            }
            if (isset($freight['packageFee'])) {
                $productPackageFee = ProductFee::query()->where('product_id', $productId)->where('type', 1)->first();
                if ($productPackageFee) {
                    $productPackageFee->fee = $freight['packageFee'];
                    $productPackageFee->update_time = Carbon::now();
                    $productPackageFee->save();
                } else {
                    $insertProductFees[] = [
                        'product_id' => $productId,
                        'type' => 1,
                        'fee' => $freight['packageFee'],
                        'create_time' => Carbon::now(),
                        'update_time' => Carbon::now(),
                    ];
                }
            }

            $handledProductIds[] = $productId;
        }

        if (!empty($insertProductFees)) {
            ProductFee::query()->insert($insertProductFees);
        }
    }

    /**
     * 创建产品
     *
     * @param array $data
     * @return array
     */
    public function createProduct($data)
    {
        $result['product_id'] = 0;
        $result['sku'] = '';

        $insertProduct['status'] = ProductStatus::WAIT_SALE; //待上架
        $insertProduct['combo_flag'] = $insertProduct['part_flag'] = 0;
        if (isset($data['combo_flag']) && $data['combo_flag'] == '1') {
            $insertProduct['combo_flag'] = 1;
        } else {
            if (isset($data['part_flag']) && $data['part_flag'] == '1') {
                $insertProduct['part_flag'] = 1;
            }
        }
        $insertProduct['buyer_flag'] = intval($data['buyer_flag']);
        if (customer()->getId()) {
            $insertProduct['model'] = CustomerPartnerToCustomer::query()->where('customer_id', customer()->getId())->value('screenname');
        }
        $insertProduct['upc'] = $data['upc'] ?? '';
        $insertProduct['ean'] = $data['ean'] ?? '';
        $insertProduct['jan'] = $data['jan'] ?? '';
        $insertProduct['isbn'] = $data['isbn'] ?? '';
        $insertProduct['mpn'] = $data['mpn'] ?? '';
        $insertProduct['location'] = $data['location'] ?? '';
        $insertProduct['quantity'] = $data['quantity'] ?? '';
        $insertProduct['minimum'] = $data['minimum'] ?? '';
        $insertProduct['subtract'] = $data['subtract'] ?? 1;
        $insertProduct['stock_status_id'] = $data['stock_status_id'] ?? '';
        $insertProduct['manufacturer_id'] = $data['manufacturer_id'] ?? '';
        $insertProduct['shipping'] = $data['shipping'] ?? '';
        if (isset($data['price'])) {
            $insertProduct['price'] = ($data['price'] == '' ? 0 : $data['price']);
        }
        $insertProduct['points'] = $data['points'] ?? '';
        if ($insertProduct['combo_flag'] !== 1) {
            if (customer()->isUSA()) {
                $weightKg = app(ProductRepository::class)->calculatePoundAndKg($data['weight'], 1, 2);
                $insertProduct['weight'] = $data['weight'];
                $insertProduct['weight_kg'] = $weightKg;
            } else {
                $weightPound = app(ProductRepository::class)->calculatePoundAndKg($data['weight'], 2, 1);
                $insertProduct['weight'] = $weightPound;
                $insertProduct['weight_kg'] = $data['weight'];
            }
            if (customer()->isUSA()) {
                $lengthCm = app(ProductRepository::class)->calculateInchesAndCm($data['length'], 1, 2);
                $insertProduct['length'] = $data['length'];
                $insertProduct['length_cm'] = $lengthCm;
            } else {
                $lengthInch = app(ProductRepository::class)->calculateInchesAndCm($data['length'], 2, 1);
                $insertProduct['length'] = $lengthInch;
                $insertProduct['length_cm'] = $data['length'];
            }
            if (customer()->isUSA()) {
                $widthCm = app(ProductRepository::class)->calculateInchesAndCm($data['width'], 1, 2);
                $insertProduct['width'] = $data['width'];
                $insertProduct['width_cm'] = $widthCm;
            } else {
                $widthInch = app(ProductRepository::class)->calculateInchesAndCm($data['width'], 2, 1);
                $insertProduct['width'] = $widthInch;
                $insertProduct['width_cm'] = $data['width'];
            }
            if (customer()->isUSA()) {
                $heightCm = app(ProductRepository::class)->calculateInchesAndCm($data['height'], 1, 2);
                $insertProduct['height'] = $data['height'];
                $insertProduct['height_cm'] = $heightCm;
            } else {
                $heightInch = app(ProductRepository::class)->calculateInchesAndCm($data['height'], 2, 1);
                $insertProduct['height'] = $heightInch;
                $insertProduct['height_cm'] = $data['height'];
            }
            //体积异常，不允许创建
            if (customer()->isUSA()) {
                if (app(ProductRepository::class)->checkChargeableWeightExceed($insertProduct['width'], $insertProduct['height'], $insertProduct['length'], $insertProduct['weight'])) {
                    return $result;
                }
            }
        } else {
            if (customer()->isUSA()) {
                $checkComboExceed = app(ProductRepository::class)->checkComboChargeableWeightExceed($data['combo'] ?? []);
                if ($checkComboExceed < 0) {
                    return $result;
                }
            }
        }
        $insertProduct['weight_class_id'] = $data['weight_class_id'] ?? '';
        $insertProduct['length_class_id'] = $data['length_class_id'] ?? '';
        $insertProduct['tax_class_id'] = $data['tax_class_id'] ?? '';
        $insertProduct['sort_order'] = $data['sort_order'] ?? '';
        $insertProduct['image'] = $data['image'] ?? '';
        // non sellable on
        $insertProduct['non_sellable_on'] = $data['non_sellable_on'] ?? null;

        if (isset($data['price_display'])) {
            $insertProduct['price_display'] = (int)$data['price_display'];
        }

        if (isset($data['quantity_display'])) {
            $insertProduct['quantity_display'] = (int)$data['quantity_display'];
        }

        $insertProduct['date_added'] = Carbon::now();
        $insertProduct['date_available'] = date('Y-m-d');

        // 33309 自定义字段
        $customFields = [];
        $certificationDocument = [];
        try {
            db()->getConnection()->beginTransaction();
            $productId = Product::query()->insertGetId($insertProduct);

            if (!empty($data['information_custom_field'])) {
                foreach ($data['information_custom_field'] as $field) {
                    $customFields[]= [
                            'product_id' => $productId,
                            'type' => ProductCustomFieldType::INFORMATION,
                            'name' => $field['name'],
                            'value' => $field['value'],
                            'sort' => $field['sort'],
                    ];
                }
            }
            if (!empty($data['dimensions_custom_field'])) {
                foreach ($data['dimensions_custom_field'] as $field) {
                    $customFields[]= [
                        'product_id' => $productId,
                        'type' => ProductCustomFieldType::DIMENSIONS,
                        'name' => $field['name'],
                        'value' => $field['value'],
                        'sort' => $field['sort'],
                    ];
                }
            }
            if (!empty($data['certification_documents'])) {
                foreach ($data['certification_documents'] as $document) {
                    $certificationDocument[]= [
                        'product_id' => $productId,
                        'type_id' => $document['type_id'],
                        'name' => $document['name'],
                        'url' => $document['url'],
                    ];
                }
            }

            if ($productId) {
                //如果是combo品添加产品  子combo里面如果有任何一个是ltl的，那么此商品需要设置成ltl
                if (isset($data['combo_flag']) && $data['combo_flag'] == 1) {
                    //内部美国seller产品B2B不再标记LTL
                    if (customer()->isUSA() && !customer()->isInnerAccount()) {
                        $comboProductIds = array_column($data['combo'], 'product_id');
                        if ($comboProductIds) { //已经标记成ltl的不处理
                            $exist = ProductToTag::query()->whereIn('product_id', $comboProductIds)
                                ->where('tag_id', (int)configDB('tag_id_oversize'))
                                ->exists();
                            if ($exist) {
                                app(ProductToTagService::class)->insertProductTag($productId, (int)configDB('tag_id_oversize'));
                            }
                        }
                    }
                    foreach ($data['combo'] as $combo) {
                        db('tb_sys_product_set_info')->insert([
                            'set_mpn' => $combo['mpn'],
                            'weight' => $combo['weight'],
                            'cubes' => 0,
                            'height' => $combo['height'],
                            'length' => $combo['length'],
                            'qty' => $combo['quantity'],
                            'width' => $combo['width'],
                            'mpn' => $data['mpn'],
                            'product_id' => $productId,
                            'seller_id' => customer()->getId(),
                            'set_product_id' => $combo['product_id'],
                        ]);
                    }
                    //添加combo标签关系
                    app(ProductToTagService::class)->insertProductTag($productId, (int)configDB('tag_id_combo'));
                } else {
                    //判断是否为配件，并添加标签关系
                    if (isset($data['part_flag']) && $data['part_flag'] == '1') {
                        app(ProductToTagService::class)->insertProductTag($productId, (int)configDB('tag_id_part'));
                    }
                    // 内部美国seller产品B2B不再标记LTL
                    if (customer()->isUSA() && !customer()->isInnerAccount()) {
                        //是否ltl发货，美国才有ltl
                        if (isset($data['is_ltl']) && $data['is_ltl'] == 1) {
                            app(ProductToTagService::class)->insertProductTag($productId, (int)configDB('tag_id_oversize'));
                            $returnFlag = ProductHelper::getProductLtlRemindLevel($insertProduct['width'], $insertProduct['length'], $insertProduct['height'], $insertProduct['weight']);
                            $operator = '';
                            if ($returnFlag == 1) {
                                $operator = customer()->getFirstName() . customer()->getLastName();
                            } elseif ($returnFlag == 2) {
                                $operator = 'system';
                            }
                            if ($operator) {
                                ProductLtlLog::query()->insert([
                                    'product_id' => $productId,
                                    'length' => $insertProduct['length'],
                                    'width' => $insertProduct['width'],
                                    'height' => $insertProduct['height'],
                                    'weight' => $insertProduct['weight'],
                                    'operator' => $operator,
                                    'create_time' => Carbon::now(),
                                ]);
                            }
                        } else {
                            $returnFlag = ProductHelper::getProductLtlRemindLevel($insertProduct['width'], $insertProduct['length'], $insertProduct['height'], $insertProduct['weight']);
                            if ($returnFlag == 2) {
                                app(ProductToTagService::class)->insertProductTag($productId, (int)configDB('tag_id_oversize'));
                                ProductLtlLog::query()->insert([
                                    'product_id' => $productId,
                                    'length' => $insertProduct['length'],
                                    'width' => $insertProduct['width'],
                                    'height' => $insertProduct['height'],
                                    'weight' => $insertProduct['weight'],
                                    'operator' => 'system',
                                    'create_time' => Carbon::now(),
                                ]);
                            }
                        }
                    }
                }

                //更新sku
                $sku = app(ProductRepository::class)->getProductSku($data['mpn'], (int)$data['combo_flag']);
                ProductExts::query()->updateOrInsert(['product_id' => $productId],
                    [
                        'is_original_design' => $data['original_product'] ?? 0,
                        'sku' => $sku,
                        'is_customize' => $data['is_customize'],
                        'origin_place_code' => $data['origin_place_code'] ?: '',
                        'filler' => $data['filler'] ?: 0,
                        'assemble_length' => $data['assemble_length'],
                        'assemble_width' => $data['assemble_width'],
                        'assemble_height' => $data['assemble_height'],
                        'assemble_weight' => $data['assemble_weight'],
                    ]);

                ProductCustomField::query()->insert($customFields);

                ProductCertificationDocument::query()->insert($certificationDocument);

                Product::query()->where('product_id', $productId)->update(['sku' => $sku]);
                //写入图片
                if (isset($data['product_image']) && $data['product_image']) {
                    foreach ($data['product_image'] as $product_image) {
                        ProductImage::query()->insert([
                            'product_id' => $productId,
                            'image' => html_entity_decode($product_image['image'], ENT_QUOTES, 'UTF-8'),
                            'sort_order' => $product_image['sort_order'],
                        ]);
                    }
                }
                //老的逻辑
                if (isset($data['product_custom_field'])) {
                    /** @var \ModelWkcustomfieldWkcustomfield $wkcustomfieldModel */
                    $wkcustomfieldModel = load()->model('account/wkcustomfield');
                    $wkcustomfieldModel->addCustomFields($data['product_custom_field'], $productId);
                }
                //写入价格
                SellerPriceHistory::query()->insert([
                    'product_id' => $productId,
                    'price' => $data['price'],
                    'add_date' => Carbon::now(),
                    'status' => 1,
                ]);
                $result['product_id'] = $productId;
                $result['sku'] = $sku;
                $result['notice_type'] = 0;
            }
            db()->getConnection()->commit();
        } catch (\Exception $ex) {
            Logger::addEditProduct('商品创建失败:' . $ex->getMessage());
            db()->getConnection()->rollback();
            return [
                'product_id' => 0,
                'sku' => '',
                'notice_type' => 0,
            ];
        }

        return $result;
    }

    /**
     * 编辑商品信息  和创建商品分开写
     * @param array $data
     * @param int $productId
     * @return array|bool
     * @throws \Exception
     */
    public function editProduct($data, int $productId)
    {
        $productInfo = Product::find($productId);
        if (!$productId || !$productInfo) {
            return false;
        }
        $updateProduct = [];
        if (customer()->getId()) {
            $updateProduct['model'] = CustomerPartnerToCustomer::query()->where('customer_id', customer()->getId())->value('screenname');
        }

        $updateProduct['upc'] = $data['upc'];
        $updateProduct['buyer_flag'] = intval($data['buyer_flag']);
        $updateProduct['minimum'] = $data['minimum'] ?? '';
        $updateProduct['subtract'] = $data['subtract'] ?? 1;
        $updateProduct['stock_status_id'] = $data['stock_status_id'] ?? '';
        if (isset($data['date_available'])) {
            $updateProduct['date_available'] = $data['date_available'];
        }
        $updateProduct['manufacturer_id'] = $data['manufacturer_id'] ?? '';
        $updateProduct['shipping'] = $data['shipping'] ?? '';
        $updateProduct['points'] = $data['points'] ?? '';
        $updateProduct['length_class_id'] = $data['length_class_id'] ?? '';
        $updateProduct['tax_class_id'] = $data['tax_class_id'] ?? '';
        $updateProduct['sort_order'] = $data['sort_order'] ?? '';
        $updateProduct['image'] = $data['image'] ?? '';
        // non sellable on
        $updateProduct['non_sellable_on'] = $data['non_sellable_on'] ?? null;

        if (isset($data['quantity_display'])) {
            $updateProduct['quantity_display'] = (int)$data['quantity_display'];
        }
        if (isset($data['need_install'])) {
            $updateProduct['need_install'] = (int)$data['need_install'];
        }
        $updateProduct['product_size'] = $data['product_size'] ?? '';
        $updateProduct['date_modified'] = Carbon::now();

        //先拿到当前商品是否ltl
        $currentProductExist = ProductToTag::query()->where('product_id', $productId)
            ->where('tag_id', (int)configDB('tag_id_oversize'))
            ->exists();

        // 自定义字段和认证文件
        $customFields = [];
        $certificationDocument = [];
        if (!empty($data['information_custom_field'])) {
            foreach ($data['information_custom_field'] as $field) {
                $customFields[]= [
                    'product_id' => $productId,
                    'type' => ProductCustomFieldType::INFORMATION,
                    'name' => $field['name'],
                    'value' => $field['value'],
                    'sort' => $field['sort'],
                ];
            }
        }
        if (!empty($data['dimensions_custom_field'])) {
            foreach ($data['dimensions_custom_field'] as $field) {
                $customFields[]= [
                    'product_id' => $productId,
                    'type' => ProductCustomFieldType::DIMENSIONS,
                    'name' => $field['name'],
                    'value' => $field['value'],
                    'sort' => $field['sort'],
                ];
            }
        }
        if (!empty($data['certification_documents'])) {
            foreach ($data['certification_documents'] as $document) {
                $certificationDocument[]= [
                    'product_id' => $productId,
                    'type_id' => $document['type_id'],
                    'name' => $document['name'],
                    'url' => $document['url'],
                ];
            }
        }

        //不允许修改商品类型和尺寸，但combo的话，可以改子combo品
        if (in_array($productInfo->status, ProductStatus::notSale())) {
            try {
                db()->getConnection()->beginTransaction();
                //分组信息变更
                /** @var \ModelAccountCustomerpartnerProductGroup $modelAcp */
                $modelAcp = load()->model('Account/Customerpartner/ProductGroup');
                $modelAcp->updateLinkByProduct(customer()->getId(), $data['product_group_ids'] ?? [], $productId);

                if ($productInfo->combo_flag == 1) { //可以改子combo品
                    db('tb_sys_product_set_info')->where('product_id', $productId)->delete();
                    // 内部美国seller产品B2B不再标记LTL
                    if (customer()->isUSA() && !customer()->isInnerAccount()) {
                        $comboProductIds = array_column($data['combo'], 'product_id');
                        if ($comboProductIds) {
                            $exist = ProductToTag::query()
                                ->whereIn('product_id', $comboProductIds)
                                ->where('tag_id', (int)configDB('tag_id_oversize'))
                                ->exists();
                            if ($exist && !$currentProductExist) {
                                app(ProductToTagService::class)->insertProductTag($productId, (int)configDB('tag_id_oversize'));
                            } elseif (!$exist && $currentProductExist) {
                                ProductToTag::query()
                                    ->where('product_id', $productId)
                                    ->where('tag_id', (int)configDB('tag_id_oversize'))
                                    ->delete();
                            }
                        }
                    }
                    foreach ($data['combo'] as $combo) {
                        $setProductId = $combo['product_id'];
                        db('tb_sys_product_set_info')->insert([
                            'set_mpn' => $combo['mpn'],
                            'weight' => $combo['weight'],
                            'cubes' => 0,
                            'height' => $combo['height'],
                            'length' => $combo['length'],
                            'qty' => $combo['quantity'],
                            'width' => $combo['width'],
                            'mpn' => $data['mpn'],
                            'product_id' => $productId,
                            'seller_id' => customer()->getId(),
                            'set_product_id' => $setProductId,
                        ]);
                    }
                }

                //商品图片
                if (isset($data['product_image']) && $data['product_image']) {
                    ProductImage::query()->where('product_id', $productId)->delete();
                    foreach ($data['product_image'] as $product_image) {
                        ProductImage::query()->insert([
                            'product_id' => $productId,
                            'image' => html_entity_decode($product_image['image'], ENT_QUOTES, 'UTF-8'),
                            'sort_order' => $product_image['sort_order'],
                        ]);
                    }
                }
                //更新商品信息
                Product::query()->where('product_id', $productId)->update($updateProduct);
                ProductExts::query()->updateOrInsert(['product_id' => $productId], [
                    'is_original_design' => $data['original_product'] ?? 0,
                    'is_customize' => $data['is_customize'],
                    'origin_place_code' => $data['origin_place_code'] ?: '',
                    'filler' => $data['filler'] ?: 0,
                    'assemble_length' => $data['assemble_length'],
                    'assemble_width' => $data['assemble_width'],
                    'assemble_height' => $data['assemble_height'],
                    'assemble_weight' => $data['assemble_weight'],
                ]);

                ProductCustomField::query()->where('product_id', $productId)->delete();
                ProductCustomField::query()->insert($customFields);

                ProductCertificationDocument::query()->where('product_id', $productId)->delete();
                ProductCertificationDocument::query()->insert($certificationDocument);

                db()->getConnection()->commit();
            } catch (\Exception $ex) {
                db()->getConnection()->rollBack();
                Logger::addEditProduct('编辑商品失败:' . $ex->getMessage());
                return false;
            }
        }

        // 关联产品
        $data['product_associated'] = app(ProductOptionService::class)->updateProductAssociate($productId, $data['product_associated'] ?? []);

        return app(ProductAuditService::class)->insertProductAuditAfterEdit($productId, $data);
    }

    /**
     * 编辑商品信息 PS:此方法包含更改商品类型和尺寸逻辑，#6446暂定php这边禁止修改，后续极有可能恢复可修改，故备份此方法并不删除代码
     * @param array $data
     * @param int $productId
     * @return array|bool
     * @throws \Exception
     */
    public function editProductBak($data, int $productId)
    {
        $productInfo = Product::find($productId);
        if (!$productId || !$productInfo) {
            return false;
        }
        $updateProduct = [];
        if (customer()->getId()) {
            $updateProduct['model'] = CustomerPartnerToCustomer::query()->where('customer_id', customer()->getId())->value('screenname');
        }

        $updateProduct['buyer_flag'] = intval($data['buyer_flag']);
        $updateProduct['quantity'] = $data['quantity'] ?? '';
        $updateProduct['minimum'] = $data['minimum'] ?? '';
        $updateProduct['subtract'] = $data['subtract'] ?? '';
        $updateProduct['stock_status_id'] = $data['stock_status_id'] ?? '';
        if (isset($data['date_available'])) {
            $updateProduct['date_available'] = $data['date_available'];
        }
        $updateProduct['manufacturer_id'] = $data['manufacturer_id'] ?? '';
        $updateProduct['shipping'] = $data['shipping'] ?? '';
        $updateProduct['points'] = $data['points'] ?? '';
        $updateProduct['length_class_id'] = $data['length_class_id'] ?? '';
        $updateProduct['tax_class_id'] = $data['tax_class_id'] ?? '';
        $updateProduct['sort_order'] = $data['sort_order'] ?? '';
        $updateProduct['image'] = $data['image'] ?? '';
        if (isset($data['quantity_display'])) {
            $updateProduct['quantity_display'] = (int)$data['quantity_display'];
        }
        if (isset($data['need_install'])) {
            $updateProduct['need_install'] = (int)$data['need_install'];
        }
        $updateProduct['product_size'] = $data['product_size'] ?? '';
        $updateProduct['date_modified'] = Carbon::now();

        //待上架和下架状态  商品可以直接修改
        // 下架不允许修改商品类型和尺寸，但可以改combo品
        if (in_array($productInfo->status, ProductStatus::notSale())) {
            try {
                db()->getConnection()->beginTransaction();
                //先拿到当前商品是否ltl
                $currentProductExist = ProductToTag::query()->where('product_id', $productId)
                    ->where('tag_id', (int)configDB('tag_id_oversize'))
                    ->exists();

                if ($productInfo->status == ProductStatus::WAIT_SALE) {
                    //先删除combo品
                    db('tb_sys_product_set_info')->where('product_id', $productId)->delete();
                    //删除标签
                    ProductToTag::query()->where('product_id', $productId)->delete();

                    $updateProduct['combo_flag'] = $updateProduct['part_flag'] = 0;
                    if (isset($data['combo_flag']) && $data['combo_flag'] == 1) {
                        $updateProduct['combo_flag'] = 1;
                    } else {
                        if (isset($data['part_flag']) && $data['part_flag'] == 1) {
                            $updateProduct['part_flag'] = 1;
                        }
                    }
                    //分组信息变更
                    /** @var \ModelAccountCustomerpartnerProductGroup $modelAcp */
                    $modelAcp = load()->model('Account/Customerpartner/ProductGroup');
                    $modelAcp->updateLinkByProduct(customer()->getId(), $data['product_group_ids'] ?? [], $productId);

                    //combo的is_ltl 前端都是传0
                    if ($updateProduct['combo_flag'] == 1) {
                        // 内部美国seller产品B2B不再标记LTL
                        if (customer()->isUSA() && !customer()->isInnerAccount()) {
                            $comboProductIds = array_column($data['combo'], 'product_id');
                            if ($comboProductIds) {
                                $exist = ProductToTag::query()->whereIn('product_id', $comboProductIds)
                                    ->where('tag_id', (int)configDB('tag_id_oversize'))
                                    ->exists();
                                if ($exist) {
                                    app(ProductToTagService::class)->insertProductTag($productId, (int)configDB('tag_id_oversize'));
                                }
                            }
                        }
                        foreach ($data['combo'] as $combo) {
                            $setProductId = $combo['product_id'];
                            db('tb_sys_product_set_info')->insert([
                                'set_mpn' => $combo['mpn'],
                                'weight' => $combo['weight'],
                                'cubes' => 0,
                                'height' => $combo['height'],
                                'length' => $combo['length'],
                                'qty' => $combo['quantity'],
                                'width' => $combo['width'],
                                'mpn' => $data['mpn'],
                                'product_id' => $productId,
                                'seller_id' => customer()->getId(),
                                'set_product_id' => $setProductId,
                            ]);
                        }
                        //添加combo标签关系
                        app(ProductToTagService::class)->insertProductTag($productId, (int)configDB('tag_id_combo'));
                        $updateProduct['length'] = $updateProduct['width'] = $updateProduct['height'] = $updateProduct['weight'] = 0;
                        $updateProduct['length_cm'] = $updateProduct['width_cm'] = $updateProduct['height_cm'] = $updateProduct['weight_kg'] = 0;
                    } else {
                        if ($updateProduct['part_flag'] == 1) {
                            app(ProductToTagService::class)->insertProductTag($productId, (int)configDB('tag_id_part'));
                        }
                        if (customer()->isUSA()) {
                            $weightKg = app(ProductRepository::class)->calculatePoundAndKg($data['weight'], 1, 2);
                            $updateProduct['weight'] = $data['weight'];
                            $updateProduct['weight_kg'] = $weightKg;
                        } else {
                            $weightPound = app(ProductRepository::class)->calculatePoundAndKg($data['weight'], 2, 1);
                            $updateProduct['weight'] = $weightPound;
                            $updateProduct['weight_kg'] = $data['weight'];
                        }
                        if (customer()->isUSA()) {
                            $lengthCm = app(ProductRepository::class)->calculateInchesAndCm($data['length'], 1, 2);
                            $updateProduct['length'] = $data['length'];
                            $updateProduct['length_cm'] = $lengthCm;
                        } else {
                            $lengthInch = app(ProductRepository::class)->calculateInchesAndCm($data['length'], 2, 1);
                            $updateProduct['length'] = $lengthInch;
                            $updateProduct['length_cm'] = $data['length'];
                        }
                        if (customer()->isUSA()) {
                            $widthCm = app(ProductRepository::class)->calculateInchesAndCm($data['width'], 1, 2);
                            $updateProduct['width'] = $data['width'];
                            $updateProduct['width_cm'] = $widthCm;
                        } else {
                            $widthInch = app(ProductRepository::class)->calculateInchesAndCm($data['width'], 2, 1);
                            $updateProduct['width'] = $widthInch;
                            $updateProduct['width_cm'] = $data['width'];
                        }
                        if (customer()->isUSA()) {
                            $heightCm = app(ProductRepository::class)->calculateInchesAndCm($data['height'], 1, 2);
                            $updateProduct['height'] = $data['height'];
                            $updateProduct['height_cm'] = $heightCm;
                        } else {
                            $heightInch = app(ProductRepository::class)->calculateInchesAndCm($data['height'], 2, 1);
                            $updateProduct['height'] = $heightInch;
                            $updateProduct['height_cm'] = $data['height'];
                        }

                        //内部美国seller产品B2B不再标记LTL
                        if (customer()->isUSA() && !customer()->isInnerAccount()) {
                            if (isset($data['is_ltl']) && $data['is_ltl'] == 1) {
                                app(ProductToTagService::class)->insertProductTag($productId, (int)configDB('tag_id_oversize'));
                                $returnFlag = ProductHelper::getProductLtlRemindLevel($updateProduct['width'], $updateProduct['length'], $updateProduct['height'], $updateProduct['weight']);
                                $operator = '';
                                if ($returnFlag == 1) {
                                    $operator = customer()->getFirstName() . customer()->getLastName();
                                } elseif ($returnFlag == 2) {
                                    $operator = 'system';
                                }
                                if ($operator && !$currentProductExist) {
                                    ProductLtlLog::query()->insert([
                                        'product_id' => $productId,
                                        'length' => $updateProduct['length'],
                                        'width' => $updateProduct['width'],
                                        'height' => $updateProduct['height'],
                                        'weight' => $updateProduct['weight'],
                                        'operator' => $operator,
                                        'create_time' => Carbon::now(),
                                    ]);
                                }
                            } else {
                                $returnFlag = ProductHelper::getProductLtlRemindLevel($updateProduct['width'], $updateProduct['length'], $updateProduct['height'], $updateProduct['weight']);
                                if ($returnFlag == 2) { //后台默认标记
                                    app(ProductToTagService::class)->insertProductTag($productId, (int)configDB('tag_id_oversize'));
                                    if (!$currentProductExist) {
                                        ProductLtlLog::query()->insert([
                                            'product_id' => $productId,
                                            'length' => $updateProduct['length'],
                                            'width' => $updateProduct['width'],
                                            'height' => $updateProduct['height'],
                                            'weight' => $updateProduct['weight'],
                                            'operator' => 'system',
                                            'create_time' => Carbon::now(),
                                        ]);
                                    }
                                } else {
                                    if ($currentProductExist) {
                                        ProductLtlLog::query()->insert([
                                            'product_id' => $productId,
                                            'ltl_type' => ProductLtlLog::LTL_TYPE_CANCEL,
                                            'length' => $updateProduct['length'],
                                            'width' => $updateProduct['width'],
                                            'height' => $updateProduct['height'],
                                            'weight' => $updateProduct['weight'],
                                            'operator' => customer()->getFirstName() . customer()->getLastName(),
                                            'create_time' => Carbon::now(),
                                        ]);
                                    }
                                }
                            }
                        }
                    }

                } else {
                    //下架状态不允许修改产品类型，但是可以编辑combo子品
                    if ($productInfo->combo_flag == 1) {
                        //先删除combo 在写入
                        db('tb_sys_product_set_info')->where('product_id', $productId)->delete();
                        //内部美国seller产品B2B不再标记LTL
                        if (customer()->isUSA() && !customer()->isInnerAccount()) {
                            $comboProductIds = array_column($data['combo'], 'product_id');
                            if ($comboProductIds) {
                                $exist = ProductToTag::query()->whereIn('product_id', $comboProductIds)
                                    ->where('tag_id', (int)configDB('tag_id_oversize'))
                                    ->exists();
                                if ($exist && !$currentProductExist) {
                                    app(ProductToTagService::class)->insertProductTag($productId, (int)configDB('tag_id_oversize'));
                                } elseif (!$exist && $currentProductExist) {
                                    ProductToTag::query()
                                        ->where('product_id', $productId)
                                        ->where('tag_id', (int)configDB('tag_id_oversize'))
                                        ->delete();
                                }
                            }
                        }
                        foreach ($data['combo'] as $combo) {
                            $setProductId = $combo['product_id'];
                            db('tb_sys_product_set_info')->insert([
                                'set_mpn' => $combo['mpn'],
                                'weight' => $combo['weight'],
                                'cubes' => 0,
                                'height' => $combo['height'],
                                'length' => $combo['length'],
                                'qty' => $combo['quantity'],
                                'width' => $combo['width'],
                                'mpn' => $data['mpn'],
                                'product_id' => $productId,
                                'seller_id' => customer()->getId(),
                                'set_product_id' => $setProductId,
                            ]);
                        }
                    }
                }
                //商品图片
                if (isset($data['product_image']) && $data['product_image']) {
                    ProductImage::query()->where('product_id', $productId)->delete();
                    foreach ($data['product_image'] as $product_image) {
                        ProductImage::query()->insert([
                            'product_id' => $productId,
                            'image' => html_entity_decode($product_image['image'], ENT_QUOTES, 'UTF-8'),
                            'sort_order' => $product_image['sort_order'],
                        ]);
                    }
                }
                //更新商品信息
                Product::query()->where('product_id', $productId)->update($updateProduct);
                db()->getConnection()->commit();
            } catch (\Exception $ex) {
                db()->getConnection()->rollBack();
                Logger::error('编辑商品失败：' . $ex->getMessage());
                return false;
            }
        }

        return app(ProductAuditService::class)->insertProductAuditAfterEdit($productId, $data);
    }

    /**
     * 参考 catalog\controller\pro\product.php resize()
     * @param string|null $imagePath 相对路径 相对于image/目录的路径
     * @param int $width
     * @param int $height
     * @return string
     */
    protected function resize(string $imagePath = null, int $width = 100, int $height = 100): string
    {
        return StorageCloud::image()->getUrl($imagePath, [
            'w' => $width,
            'h' => $height,
            'no-image' => static::DEFAULT_BLANK_IMAGE,
        ]);
    }


    /**
     * 处理图片相关信息
     * 参考 catalog\controller\pro\product.php resolveImageItem()
     * @param array $item
     * @param bool $isMaterialFiles
     */
    function resolveImageItem(array &$item, $isMaterialFiles = false)
    {
        if (stripos($item['orig_url'], 'http') === 0) {
            // http 的路径不处理
            return;
        }
        if ($isMaterialFiles) {
            if (preg_match('/^(\d+)\/(\d+)\/(file|image|video)\/(.*)/', $item['orig_url'])) {
                // 兼容原 根目录/productPackage 下的文件
                // 57/10821/image/1547113262_1.jpg 之前的写法是这样的
                $item['replace_url'] = $item['orig_url'];
                $item['orig_url'] = 'productPackage/' . $item['orig_url'];
            }
        }
        $item['thumb'] = $this->resize($item['orig_url'], 100, 100);
        $item['is_blank'] = (int)(strpos($item['thumb'], static::DEFAULT_BLANK_IMAGE) !== false);
        if (!$item['is_blank']) {
            $item['url'] = StorageCloud::image()->getUrl($item['orig_url'], ['check-exist' => false]);
        }
    }

    /**
     * 如果是不可售卖商品，可以直接上架 [新增商品时候，如果是选了直接提交审核，那么直接上架，提交草稿，则正常为待上架状态]
     * @param array $productResult
     * @param array $postData
     * @return bool
     */
    public function resetProductInfo($productResult, $postData)
    {
        if (empty($postData) || !in_array($postData['buyer_flag'], [0, 1])) {
            return true;
        }
        $productInfo = Product::query()->find($productResult['product_id']);
        if ($productInfo && in_array($productInfo->status, ProductStatus::notSale()) && $postData['buyer_flag'] == 0) {
            //is_draft  1:提交草稿  2:提交审核  3:编辑时候传的值
            if ($postData['is_draft'] == 2 || ($postData['is_draft'] == 3 && $productResult['notice_type'] == 3)) {
                $productInfo->status = ProductStatus::ON_SALE;
                $productInfo->save();
            }
        }

        return true;
    }


    /**
     * 获取产品分类名称
     * @param int $productId
     * @return mixed|string
     */
    public function getCategoryNameByProductId($productId)
    {
        $category = db('oc_product_to_category')->where('product_id', $productId)->first();
        if (empty($category)) {
            return '';
        }
        return CategoryDescription::query()->where('category_id', $category->category_id)->value('name');
    }

    /**
     * 获取产品的主题
     * @param int $productId
     * @return \Illuminate\Support\Collection
     */
    public function getProductImages($productId)
    {
        return db('oc_product_image')->where('product_id', $productId)->orderBy('sort_order', 'ASC')->get();
    }

    /**
     * 获取产品的图片
     * @param int|Product $productId
     * @return array
     */
    public function getProductImageAndMain($productId)
    {
        $product = is_object($productId) ? $productId : Product::find($productId);
        return $product->images->take(9)->pluck('image')->toArray();
    }

    /**
     * 发送打包请求
     * @param int $sellerId
     * @param int $productId
     * @return \Symfony\Contracts\HttpClient\ResponseInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    public function packedZip($sellerId, $productId)
    {
        $client = HttpClient::create();
        $url = URL_TASK_WORK . '/api/product/packed';
        return $client->request('POST', $url, [
            'json' => [
                'data' => [
                    ['customer_id' => $sellerId, 'product_id' => $productId]
                ]
            ],
        ]);
    }

    /**
     * 校验该产品是否属于giga onsite seller
     * @param int $productId
     * @return bool
     * @throws InvalidArgumentException
     */
    public function checkIsGigaOnsiteProduct(int $productId): bool
    {
        $cacheKey = [__CLASS__, __FUNCTION__, __METHOD__, $productId];
        if (cache()->has($cacheKey)) {
            return cache($cacheKey) == 1;
        }
        $product = Product::query()->with(['customerPartner'])->find($productId);
        cache()->set(
            $cacheKey,
            $product->customerPartner->accounting_type == CustomerAccountingType::GIGA_ONSIDE ? 1 : 0,
            6000
        );
        return cache($cacheKey) == 1;
    }
}
