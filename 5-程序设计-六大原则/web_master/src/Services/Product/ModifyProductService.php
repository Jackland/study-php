<?php

namespace App\Services\Product;

use App\Catalog\Forms\Product\Import\ModifyValidate;
use App\Components\BatchInsert;
use App\Components\Storage\StorageCloud;
use App\Enums\Product\ProductAuditStatus;
use App\Enums\Product\ProductAuditType;
use App\Enums\Product\ProductImportType;
use App\Enums\Product\ProductStatus;
use App\Helper\ProductHelper;
use App\Logging\Logger;
use App\Models\Customer\Country;
use App\Models\Link\ProductToCategory;
use App\Models\Product\Option\Option;
use App\Models\Product\Option\ProductImage;
use App\Models\Product\Option\ProductPackageFile;
use App\Models\Product\Option\ProductPackageImage;
use App\Models\Product\Option\ProductPackageVideo;
use App\Models\Product\Product;
use App\Models\Product\ProductAudit;
use App\Models\Product\ProductDescription;
use App\Models\Product\ProductExts;
use App\Models\Product\ProductImportBatch;
use App\Models\Product\ProductImportBatchErrorReport;
use App\Models\Product\ProductSetInfo;
use App\Repositories\Product\Option\OptionValueRepository;
use App\Repositories\Product\ProductOptionRepository;
use App\Repositories\Product\ProductRepository;
use App\Services\Store\StoreSelectedCategoryService;
use Carbon\Carbon;

class ModifyProductService
{
    /**
     * 编辑导入的产品
     * @param array $products
     * @param int $customerId
     * @param int $country
     * @param string $fileName
     * @return array
     */
    public function editImportProducts(array $products, int $customerId, int $country, string $fileName): array
    {
        /** @var ProductOptionRepository $productOptionRepository */
        $productOptionRepository = app(ProductOptionRepository::class);
        $colorOptionIdNameMap = array_map('strtolower', $productOptionRepository->getOptionsById(Option::COLOR_OPTION_ID)->pluck('name', 'option_value_id')->toArray());
        $materialOptionIdNameMap = array_map('strtolower', $productOptionRepository->getOptionsById(Option::MATERIAL_OPTION_ID)->pluck('name', 'option_value_id')->toArray());
        // 通过和错误的产品区分
        $correctProducts = [];
        $errorProducts = [];
        $modifyFormValidate = new ModifyValidate($products, $customerId, $country, $colorOptionIdNameMap, $materialOptionIdNameMap);
        foreach ($products as $product) {
            $analysisedProduct = $modifyFormValidate->validate($product);
            //写入错误日志还得用老数据写入
            $product['Old Supporting Files Path'] = $product['Supporting Files Path'] ?? '';
            $product['Old Main Image Path'] = $product['Main Image Path'] ?? '';
            $product['Old Images Path(to be displayed)'] = $product['Images Path(to be displayed)'] ?? '';
            $product['Old Images Path(other material)'] = $product['Images Path(other material)'] ?? '';
            $product['Old Material Manual Path'] = $product['Material Manual Path'] ?? '';
            $product['Old Material Video Path'] = $product['Material Video Path'] ?? '';

            $product['current_product_id'] = $analysisedProduct['current_product_id']; //商品id
            $product['status'] = $analysisedProduct['status']; //商品状态
            $product['combo_flag'] = $analysisedProduct['combo_flag']; //combo标记
            $product['product_old_title'] = $analysisedProduct['product_old_title']; //老的商品标题

            if ($analysisedProduct['can_update'] == 1 && $product['current_product_id'] > 0) {
                if (isset($analysisedProduct['part_product']) && !empty($analysisedProduct['part_product'])) {
                    $product = array_merge($product, $analysisedProduct['part_product']);
                }
                $correctProducts[] = $product;
                if (isset($analysisedProduct['errors']) && !empty($analysisedProduct['errors'])) {
                    $product['error_content'] = join(';', $analysisedProduct['errors']);
                    $errorProducts[] = $product;
                }
            } else {
                if (isset($analysisedProduct['errors']) && !empty($analysisedProduct['errors'])) {
                    $product['error_content'] = join(';', $analysisedProduct['errors']);
                }
                $errorProducts[] = $product;
            }
        }

        $idProductMap = [];
        $addNocomboProductIds = []; //处理美国产品信息同步->需要同步OMD
        $notOnSaleProductIds = []; //未上架的商品->需要处理运费
        foreach ($correctProducts as $correctProduct) {
            $correctProduct['color_option_id'] = array_search(strtolower($correctProduct['Color']), $colorOptionIdNameMap);
            $correctProduct['material_option_id'] = array_search(strtolower($correctProduct['Material']), $materialOptionIdNameMap);
            $correctProduct['filler_option_id'] = array_search(strtolower($correctProduct['Filler']), $materialOptionIdNameMap);
            try {
                dbTransaction(function () use ($correctProduct, $country, $colorOptionIdNameMap, $materialOptionIdNameMap, &$idProductMap, &$notOnSaleProductIds, &$addNocomboProductIds) {
                    $returnProductId = $this->modifyProduct($correctProduct);
                    if ($returnProductId) {
                        //需要同步运费的数组
                        if (in_array($correctProduct['status'], ProductStatus::notSale()) && $correctProduct['current_product_id'] > 0) {
                            $notOnSaleProductIds[$correctProduct['current_product_id']] = $correctProduct['current_product_id'];
                        }
                        //需要同步OMD的数组  名字改了才有同步的可能，且名称不应该频繁改动，不频繁改动很有可能就是留空
                        if (!empty($correctProduct['Product Title'])) {
                            if (customer()->isUSA() && !customer()->isInnerAccount() && $correctProduct['combo_flag'] == 0 && in_array($correctProduct['status'], ProductStatus::notSale())) {
                                if (trim($correctProduct['Product Title']) !== trim($correctProduct['product_old_title'])) {
                                    $addNocomboProductIds[] = $correctProduct['current_product_id'];
                                }
                            }
                        }
                        $idProductMap[] = $returnProductId;
                    }
                });
            } catch (\Throwable $e) {
                Logger::importProducts('批量修改某个产品失败:' . $e->getMessage(), 'error');
                Logger::importProducts('批量修改某个产品失败(详细)', 'error', [
                    Logger::CONTEXT_VAR_DUMPER => ['batch_modify_product_detail' => $e],
                ]);
                $errorProducts[] = $correctProduct;
            }
        }
        // 处理运费接口
        if ($notOnSaleProductIds) {
            app(ProductService::class)->updateProductsFreight($notOnSaleProductIds);
        }
        //处理美国产品信息同步
        if ($addNocomboProductIds) {
            try {
                ProductHelper::sendSyncProductsRequest(array_unique($addNocomboProductIds));
            } catch (\Throwable $e) {
                Logger::syncProducts($e->getMessage());
            }
        }

        $productImportBatch = new ProductImportBatch();
        $productImportBatch->customer_id = $customerId;
        $productImportBatch->file_name = $fileName;
        $productImportBatch->type = ProductImportType::BATCH_UPDATE;
        $productImportBatch->product_count = count($products);
        $productImportBatch->product_error_count = count($errorProducts);
        $productImportBatch->create_time = Carbon::now();
        $productImportBatch->save();

        $this->insertProductImportBatchErrorReport($errorProducts, $customerId, $productImportBatch->id);

        return [$idProductMap, $productImportBatch];
    }

    /**
     * 添加某个导入批次的错误数据
     * @param array $errorProducts
     * @param int $customerId
     * @param int $batchId
     */
    private function insertProductImportBatchErrorReport(array $errorProducts, int $customerId, int $batchId)
    {
        try {
            dbTransaction(function () use ($errorProducts, $batchId, $customerId) {
                $batchInsert = new BatchInsert();
                $batchInsert->begin(ProductImportBatchErrorReport::class, 500);
                $createTime = Carbon::now();
                foreach ($errorProducts as $errorProduct) {
                    $extendsInfo = [
                        'main_image_path' => trim($errorProduct['Old Main Image Path'] ?? ''),
                        'supporting_files_path' => trim($errorProduct['Old Supporting Files Path'] ?? ''),
                        'images_path_to_be_display' => trim($errorProduct['Old Images Path(to be displayed)'] ?? ''),
                        'images_path_other_material' => trim($errorProduct['Old Images Path(other material)'] ?? ''),
                        'material_manual_path' => trim($errorProduct['Old Material Manual Path'] ?? ''),
                        'material_video_path' => trim($errorProduct['Old Material Video Path'] ?? ''),
                        'upc' => $errorProduct['UPC'],
                        'customized' => $errorProduct['Customized'],
                        'place_or_origin' => $errorProduct['Place of Origin'],
                        'filler' => $errorProduct['Filler'],
                        'assembled_length' => $errorProduct['Assembled Length'],
                        'assembled_width' => $errorProduct['Assembled Width'],
                        'assembled_height' => $errorProduct['Assembled Height'],
                        'assembled_weight' => $errorProduct['Assembled Weight'],
                    ];
                    $batchInsert->addRow([
                        'customer_id' => $customerId,
                        'batch_id' => $batchId,
                        'category_id' => $errorProduct['Category ID'] ?? 0, // 0一般代表没传，即不修改category
                        'mpn' => $errorProduct['MPN'],
                        'sold_separately' => $errorProduct['Sold Separately'] ?? '',
                        'not_sale_platform' => $errorProduct['Not available for sale on'] ?? '',
                        'product_title' => $errorProduct['Product Title'] ?? '',
                        'color' => $errorProduct['Color'] ?? '',
                        'material' => $errorProduct['Material'],
                        'assemble_is_required' => '',
                        'product_size' => '',
                        'product_type' => '',
                        'sub_items' => '',
                        'sub_items_quantity' => '',
                        'length' => '',
                        'width' => '',
                        'height' => '',
                        'weight' => '',
                        'current_price' => '',
                        'display_price' => '',
                        'origin_design' => $errorProduct['Original Design'] ?? '',
                        'description' => trim($errorProduct['Product Description'] ?? ''),
                        'error_content' => $errorProduct['error_content'] ?? 'modify product error',
                        'extends_info' => json_encode($extendsInfo),
                        'create_time' => $createTime,
                    ]);
                }
                $batchInsert->end();
            });
        } catch (\Throwable $e) {
            Logger::importProducts('批量导入(修改商品)错误报告添加失败:' . $e->getMessage(), 'error');
        }
    }

    /**
     * 修改产品
     * @param array $product
     * @return bool|int|mixed
     * @throws \Exception
     */
    protected function modifyProduct(array $product)
    {
        $productId = $product['current_product_id'] ?? 0;
        $customerId = customer()->getId();
        $status = $product['status']; //商品状态

        //还得再查一遍数据库  写入审核表
        $currentProductInfo = Product::query()->with(['description', 'ext'])->find(intval($productId));
        if (empty($productId) || empty($product) || empty($currentProductInfo)) {
            throw  new \Exception('The product does not exist.');
        }

        //统一处理 曾选择过的类目操作
        app(StoreSelectedCategoryService::class)->handleSelectedCategory($productId, intval($product['Category ID'] ?? 0), $customerId);

        //没有退返品的 单独售卖&没有主图的 提交不了审核
        $firstCheck = 0;
        if (empty($currentProductInfo->description->return_warranty) || empty($currentProductInfo->description->return_warranty_text)) {
            $firstCheck = 1;
        }
        if ($currentProductInfo->buyer_flag == 1 && empty($currentProductInfo->image)) {
            $firstCheck = 1;
        }

        //直接修改资料
        if (in_array($status, ProductStatus::notSale())) {
            //更新商品信息
            $this->updateCurrentProduct($productId, $product, $currentProductInfo);
            if ($firstCheck == 1) {
                Logger::importProducts("批量修改商品时,未上架的商品ID:{$productId}，没有退返品或可单独售卖但没有主图，程序未自动提交审核");
                return $productId;
            }
            //是否需要提交审核记录
            $res = $this->doSubmitAudit($productId, $product);
            if ($res == 2) {
                return $this->createAuditRecord($productId, $product, $currentProductInfo);
            }

            return $productId;
        }

        if ($firstCheck == 1) {
            Logger::importProducts("批量修改商品时,上架中的商品ID:{$productId}，没有退返品或可单独售卖但没有主图，程序未自动提交审核2");
            return $productId;
        }

        return $this->createAuditRecord($productId, $product, $currentProductInfo);
    }

    /**
     * 更新商品信息
     * @param int $productId
     * @param array $productInfo
     * @param Product $currentProductInfo
     * @return bool
     * @throws \Exception
     */
    public function updateCurrentProduct(int $productId, array $productInfo, Product $currentProductInfo)
    {
        //分类
        $this->handleProductCategory($productId, intval($productInfo['Category ID'] ?? 0));
        //商品描述 商品名称 等
        $this->handleProductDescription($productId, $productInfo, $currentProductInfo);
        //专利 & 专利图片
        $this->handleProductOriginalDesign($productId, $productInfo);
        //商品基础信息 + 主图
        $this->handleProductBaseInfo($productId, $productInfo);
        //颜色+材质
        $this->handleProductColorAndMaterial($productId, $productInfo);
        //页面展示图片 + 主图
        $this->handleImageToBeDisplay($productId, $productInfo);
        //其它图片
        $this->handleOtherMaterial($productId, $productInfo);
        //手册素材
        $this->handleMaterialManual($productId, $productInfo);
        //视频素材
        $this->handleVideo($productId, $productInfo);

        return true;
    }

    /**
     * 商品分类
     * @param int $productId
     * @param int $categoryId
     * @return bool
     * @throws \Exception
     */
    private function handleProductCategory(int $productId, int $categoryId)
    {
        if ($categoryId) {
            ProductToCategory::query()->where('product_id', $productId)->delete();
            ProductToCategory::query()->insert(['product_id' => $productId, 'category_id' => $categoryId]);
        }

        return true;
    }

    /**
     * 商品描述等属性处理
     * @param int $productId
     * @param array $productDetail
     * @param Product $currentProductInfo
     * @return bool
     */
    private function handleProductDescription(int $productId, array $productDetail, Product $currentProductInfo)
    {
        $updateInfo = [];
        if ($currentProductInfo->description) {
            if (trim($productDetail['Product Description'])) {
                $updateInfo['description'] = trim($productDetail['Product Description']);
            }
            if (trim($productDetail['Product Title'])) {
                $updateInfo['name'] = trim($productDetail['Product Title']);
                $updateInfo['meta_title'] = trim($productDetail['Product Title']);
            }
            if ($updateInfo) {
                ProductDescription::query()->where('product_id', $productId)->update($updateInfo);
            }
        }

        return true;
    }

    /**
     * 商品专利处理 & 专利图片
     * @param int $productId
     * @param array $productDetail
     * @return bool
     */
    private function handleProductOriginalDesign(int $productId, array $productDetail)
    {
        $updateInfo = [];
        $productExt = ProductExts::query()->where('product_id', $productId)->first();
        if ($productExt) {
            if (isset($productDetail['Original Design']) && !empty($productDetail['Original Design'])) {
                $updateInfo['is_original_design'] = strtolower($productDetail['Original Design']) == 'yes' ? 1 : 0;
            }
            if (isset($productDetail['Customized']) && !empty($productDetail['Customized'])) {
                $updateInfo['is_customize'] = strtolower($productDetail['Customized']) == 'yes' ? 1 : 0;
            }
            if (isset($productDetail['Place of Origin']) && !empty($productDetail['Place of Origin'])) {
                $updateInfo['origin_place_code'] = Country::queryRead()->where('name', $productDetail['Place of Origin'])->value('iso_code_3');
            }
            if (isset($productDetail['filler_option_id']) && !empty($productDetail['filler_option_id'])) {
                $updateInfo['filler'] = $productDetail['filler_option_id'];
            }
            if (isset($productDetail['Assembled Length']) && !empty($productDetail['Assembled Length'])) {
                $updateInfo['assemble_length'] = strtolower($productDetail['Assembled Length']) == 'not applicable' ? -1.00 : $productDetail['Assembled Length'];
            }
            if (isset($productDetail['Assembled Width']) && !empty($productDetail['Assembled Width'])) {
                $updateInfo['assemble_width'] = strtolower($productDetail['Assembled Width']) == 'not applicable' ? -1.00 : $productDetail['Assembled Width'];
            }
            if (isset($productDetail['Assembled Height']) && !empty($productDetail['Assembled Height'])) {
                $updateInfo['assemble_height'] = strtolower($productDetail['Assembled Height']) == 'not applicable' ? -1.00 : $productDetail['Assembled Height'];
            }
            if (isset($productDetail['Assembled Weight']) && !empty($productDetail['Assembled Weight'])) {
                $updateInfo['assemble_weight'] = strtolower($productDetail['Assembled Weight']) == 'not applicable' ? -1.00 : $productDetail['Assembled Weight'];
            }
            $oldIsOriginalDesign = intval($productExt->is_original_design);
            if ($updateInfo) {
                ProductExts::query()->where('product_id', $productId)->update($updateInfo);
            }

            //没填写 Original Design  ,这儿的逻辑以Original Design为核心判断
            if (!isset($updateInfo['is_original_design'])) {
                if ($oldIsOriginalDesign == 1) {
                    if (!empty($productDetail['Supporting Files Path'])) {
                        db('oc_product_package_original_design_image')->where('product_id', $productId)->delete();
                        app(ProductAttachmentService::class)->addProductSupportFiles($productId, $productDetail['Supporting Files Path']);
                    }
                } else {
                    db('oc_product_package_original_design_image')->where('product_id', $productId)->delete();
                }
            } else {
                if ($updateInfo['is_original_design'] == 1) {
                    if (!empty($productDetail['Supporting Files Path'])) {
                        db('oc_product_package_original_design_image')->where('product_id', $productId)->delete();
                        app(ProductAttachmentService::class)->addProductSupportFiles($productId, $productDetail['Supporting Files Path']);
                    }
                } else {
                    db('oc_product_package_original_design_image')->where('product_id', $productId)->delete();
                }
            }
        }

        return true;
    }

    /**
     * 商品主表信息
     * @param int $productId
     * @param array $productDetail
     * @return bool
     */
    private function handleProductBaseInfo(int $productId, array $productDetail)
    {
        $baseProductInfo = [];
        if (isset($productDetail['UPC']) && !empty($productDetail['UPC'])) {
            $baseProductInfo['upc'] = $productDetail['UPC'];
        }
        //是否单独售卖
        if (isset($productDetail['Sold Separately']) && !empty($productDetail['Sold Separately'])) {
            $baseProductInfo['buyer_flag'] = strtolower($productDetail['Sold Separately']) == 'yes' ? 1 : 0;
        }
        //不可销售平台
        if (isset($productDetail['Not available for sale on']) && !empty($productDetail['Not available for sale on'])) {
            $baseProductInfo['non_sellable_on'] = $productDetail['Not available for sale on'];
        }
        if (isset($productDetail['Product Size']) && !empty($productDetail['Product Size'])) {
            $baseProductInfo['product_size'] = $productDetail['Product Size'];
        }
        //主图
        if (isset($productDetail['Main Image Path'][0]['file_real_path'])) {
            $baseProductInfo['image'] = StorageCloud::image()->getRelativePath($productDetail['Main Image Path'][0]['file_real_path']);
        }
        if ($baseProductInfo) {
            Product::query()->where('product_id', $productId)->update($baseProductInfo);
        }

        return true;
    }

    /**
     * 颜色材质
     * @param int $productId
     * @param array $productDetail
     * @return bool
     */
    private function handleProductColorAndMaterial(int $productId, array $productDetail)
    {
        //颜色
        if (isset($productDetail['color_option_id']) && $productDetail['color_option_id'] > 0) {
            $oldColorOption = Option::MIX_OPTION_ID; //13
            $newColorOption = Option::COLOR_OPTION_ID; //14
            $colorOptionId = (int)$productDetail['color_option_id'];
            app(ProductOptionService::class)->delOptionAndValueInfo($productId, [$oldColorOption, $newColorOption]);
            app(ProductOptionService::class)->insertProductOptionValue($productId, $newColorOption, $colorOptionId);
        }
        //材质
        if (isset($productDetail['material_option_id']) && $productDetail['material_option_id'] > 0) {
            $newMaterialOption = Option::MATERIAL_OPTION_ID; //15
            $materialOptionId = (int)$productDetail['material_option_id'];
            app(ProductOptionService::class)->delOptionAndValueInfo($productId, [$newMaterialOption]);
            app(ProductOptionService::class)->insertProductOptionValue($productId, $newMaterialOption, $materialOptionId);
        }

        return true;
    }

    /**
     * 页面展示图片 （主图也需要特殊处理）
     * @param int $productId
     * @param array $productDetail
     * @return bool
     * @throws \Exception
     */
    private function handleImageToBeDisplay(int $productId, array $productDetail)
    {
        if (!empty($productDetail['Images Path(to be displayed)'])) {
            if (isset($productDetail['Main Image Path'][0]['file_real_path'])) {
                $mainImageUrl = $productDetail['Main Image Path'][0]['file_real_path']; //携带image/
            } else {
                $productImage = ProductImage::query()->where('product_id', $productId)->where('sort_order', 0)->first();
                $mainImageUrl = $productImage ? $productImage->image : ''; //不带image/   但是经过getRelativePath 处理效果一致
            }
            ProductImage::query()->where('product_id', $productId)->delete();
            app(ProductAttachmentService::class)->addProductImages($productId, $productDetail['Images Path(to be displayed)'], $mainImageUrl);
        } else {
            //主图变更 页面展示图片不变更
            if (isset($productDetail['Main Image Path'][0]['file_real_path'])) {
                $mainImageUrl = StorageCloud::image()->getRelativePath($productDetail['Main Image Path'][0]['file_real_path']);
                $originProductImages = ProductImage::query()->where('product_id', $productId)->orderBy('sort_order')->get()->toArray();
                //为了复用 addProductImages方法 重组下数组 有主图时候 直接清掉数据，重新写入，确保sort顺序
                $originProductImages = array_map(function ($item) {
                    return [
                        'file_real_path' => $item['image'],
                    ];
                }, $originProductImages);
                ProductImage::query()->where('product_id', $productId)->delete();
                app(ProductAttachmentService::class)->addProductImages($productId, $originProductImages, $mainImageUrl);
            }
        }

        return true;
    }

    /**
     * 其他图片
     * @param int $productId
     * @param array $productDetail
     * @return bool
     * @throws \Exception
     */
    private function handleOtherMaterial(int $productId, array $productDetail)
    {
        if (isset($productDetail['Images Path(other material)']) && !empty($productDetail['Images Path(other material)'])) {
            ProductPackageImage::query()->where('product_id', $productId)->delete();
            app(ProductAttachmentService::class)->addProductMaterialPackageImages($productId, $productDetail['Images Path(other material)']);
        }
        return true;
    }

    /**
     * 手册素材
     * @param int $productId
     * @param array $productDetail
     * @return bool
     * @throws \Exception
     */
    private function handleMaterialManual(int $productId, array $productDetail)
    {
        if (isset($productDetail['Material Manual Path']) && !empty($productDetail['Material Manual Path'])) {
            ProductPackageFile::query()->where('product_id', $productId)->delete();
            app(ProductAttachmentService::class)->addProductMaterialManualsFile($productId, $productDetail['Material Manual Path']);
        }
        return true;
    }

    /**
     * 视频素材
     * @param int $productId
     * @param array $productDetail
     * @return bool
     * @throws \Exception
     */
    private function handleVideo(int $productId, array $productDetail)
    {
        if (isset($productDetail['Material Video Path']) && !empty($productDetail['Material Video Path'])) {
            ProductPackageVideo::query()->where('product_id', $productId)->delete();
            app(ProductAttachmentService::class)->addProductMaterialPackageVideo($productId, $productDetail['Material Video Path']);
        }
        return true;
    }

    /**
     * 是否需要提交审核记录
     * @param int $productId
     * @param array $productDetail
     * @return int  1：不需要提交审核  2：需要提交审核
     */
    private function doSubmitAudit(int $productId, array $productDetail)
    {
        $waitCheckExist = ProductAudit::query()
            ->where('product_id', $productId)
            ->where('status', ProductAuditStatus::PENDING)
            ->where('audit_type', ProductAuditType::PRODUCT_INFO)
            ->where('is_delete', 0)
            ->exists();

        if (strtolower($productDetail['Sold Separately']) == 'no') {
            //当把商品编辑成不可单独售卖时：
            //待上架&已下架：如果有待审核的记录，则删除待审核信息，商品直接上架。如果没有待审核的记录，则只保存信息。不做上架处理。
            if (!$waitCheckExist) {
                return 1;
            }
            //有待审核的记录，删除审核记录&直接上架
            ProductAudit::query()
                ->where('product_id', $productId)
                ->where('audit_type', ProductAuditType::PRODUCT_INFO)
                ->where('status', ProductAuditStatus::PENDING)
                ->update(['is_delete' => 1]);

            Product::query()->where('product_id', $productId)->update(['status' => ProductStatus::ON_SALE]);

            return 1;
        }

        //当把商品编辑成可单独售卖
        //如果有待审核的记录，则删除待审核信息，新插入一条审核记录。走审核逻辑。
        //如果没有待审核的记录，只保存信息。
        if (!$waitCheckExist) {
            return 1;
        }

        return 2;
    }

    /**
     * 提交上架审核(未上架的+上架的）
     * @param int $productId
     * @param array $productDetail
     * @param Product $currentProductInfo
     * @return int
     * @throws \Exception
     */
    public function createAuditRecord(int $productId, array $productDetail, Product $currentProductInfo)
    {
        $customerId = customer()->getId();

        $categoryId = $productDetail['Category ID'] ?? 0;
        if (empty($categoryId)) {
            $categoryDetail = ProductToCategory::query()->where('product_id', $productId)->first();
            $categoryId = (int)optional($categoryDetail)->category_id;
        }
        //颜色+材质
        $colorOptionValueId = $productDetail['color_option_id'] ?? 0;
        $materialOptionValueId = $productDetail['material_option_id'] ?? 0;
        if (empty($colorOptionValueId)) {
            $color = app(OptionValueRepository::class)->getOptionValueInfo($productId, Option::COLOR_OPTION_ID);
            if ($color) {
                $colorOptionValueId = $color->option_value_id;
            }
        }
        if (empty($materialOptionValueId)) {
            $material = app(OptionValueRepository::class)->getOptionValueInfo($productId, Option::MATERIAL_OPTION_ID);
            if ($material) {
                $materialOptionValueId = $material->option_value_id;
            }
        }

        if (isset($productDetail['Sold Separately']) && !empty($productDetail['Sold Separately'])) {
            $buyerFlag = strtolower($productDetail['Sold Separately']) == 'yes' ? 1 : 0;
        } else {
            $buyerFlag = $currentProductInfo->buyer_flag;
        }

        $mainImage = !empty($productDetail['Main Image Path'][0]['file_real_path']) ? $productDetail['Main Image Path'][0]['file_real_path'] : $currentProductInfo->image;
        $forbiddenSalesOn = !empty($productDetail['Not available for sale on']) ? $productDetail['Not available for sale on'] : $currentProductInfo->non_sellable_on;
        $productTitle = !empty($productDetail['Product Title']) ? $productDetail['Product Title'] : $currentProductInfo->description->name;

        /** @var \ModelAccountCustomerpartnerProductGroup $macao */
        $macao = load()->model('Account/Customerpartner/ProductGroup');

        $groupInfos = $macao->getGroupIDsByProductIDs($customerId, [$productId]);
        $associateProducts = $currentProductInfo->associatesProducts()->where('associate_product_id', '!=', $productId)->pluck('associate_product_id')->toArray();;

        $isCustomize = $currentProductInfo->ext ? $currentProductInfo->ext->is_customize : 0;
        $isCustomize = !empty($productDetail['Customized']) ? strtolower($productDetail['Customized']) == 'yes' : $isCustomize;

        $originPlaceCode = $currentProductInfo->ext ? $currentProductInfo->ext->origin_place_code : 0;
        $originPlaceCode = !empty($productDetail['Place of Origin']) ? array_search(strtolower($productDetail['Place of Origin']), array_map('strtolower', Country::getCodeNameMap())) : $originPlaceCode;

        $filler = $currentProductInfo->ext ? $currentProductInfo->ext->filler : 0;
        $filler = $productDetail['filler_option_id'] ?: $filler;

        $assembleLength = $currentProductInfo->ext ? $currentProductInfo->ext->assemble_length : '';
        $assembleWidth = $currentProductInfo->ext ? $currentProductInfo->ext->assemble_width : '';
        $assembleHeight = $currentProductInfo->ext ? $currentProductInfo->ext->assemble_height : '';
        $assembleWeight = $currentProductInfo->ext ? $currentProductInfo->ext->assemble_weight : '';
        if (isset($productDetail['Assembled Length']) && !empty($productDetail['Assembled Length'])) {
            $assembleLength = strtolower($productDetail['Assembled Length']) == 'not applicable' ? -1.00 : $productDetail['Assembled Length'];
        }
        if (isset($productDetail['Assembled Width']) && !empty($productDetail['Assembled Width'])) {
            $assembleWidth = strtolower($productDetail['Assembled Width']) == 'not applicable' ? -1.00 : $productDetail['Assembled Width'];
        }
        if (isset($productDetail['Assembled Height']) && !empty($productDetail['Assembled Height'])) {
            $assembleHeight = strtolower($productDetail['Assembled Height']) == 'not applicable' ? -1.00 : $productDetail['Assembled Height'];
        }
        if (isset($productDetail['Assembled Weight']) && !empty($productDetail['Assembled Weight'])) {
            $assembleWeight = strtolower($productDetail['Assembled Weight']) == 'not applicable' ? -1.00 : $productDetail['Assembled Weight'];
        }

        $assembleInfo = [
            'assemble_length' => $assembleLength,
            'assemble_width' => $assembleWidth,
            'assemble_height' => $assembleHeight,
            'assemble_weight' => $assembleWeight,
            'custom_field' => $currentProductInfo->dimensionCustomFields()->select(['name', 'value', 'sort'])->get()
        ];

        $information = [
            'upc' => !empty($productDetail['UPC']) ? $productDetail['UPC'] : $currentProductInfo->upc,
            'color_option_id' => $colorOptionValueId,
            'material_option_id' => $materialOptionValueId,
            'sold_separately' => $buyerFlag,
            'title' => $productTitle,
            'is_customize' => $isCustomize,
            'origin_place_code' => $originPlaceCode,
            'filler' => $filler,
            'custom_field' => $currentProductInfo->informationCustomFields()->select(['name', 'value', 'sort'])->get(),
            'current_price' => $currentProductInfo->price, //编辑时候 商品原价
            'display_price' => $currentProductInfo->price_display,
            'group_id' => $groupInfos ?: [],
            'image' => StorageCloud::image()->getRelativePath($mainImage),
            'non_sellable_on' => is_array($forbiddenSalesOn) ? implode(',', $forbiddenSalesOn) : $forbiddenSalesOn,
        ];

        if ($currentProductInfo->combo_flag == 1) {
            $combos = ProductSetInfo::query()->where('product_id', $productId)->get();
            foreach ($combos as $val) {
                $combo[] = ['product_id' => $val->set_product_id, 'quantity' => $val->qty];
            }
            $productInfo = [
                'type_id' => 2,
                'combo' => $combo,
                'no_combo' => [
                    'length' => 0,
                    'width' => 0,
                    'height' => 0,
                    'weight' => 0
                ],
            ];
        } else {
            $productInfo = [
                'type_id' => $currentProductInfo->part_flag == 1 ? 3 : 1,
                'combo' => [],
                'no_combo' => [
                    'length' => customer()->isUSA() ? $currentProductInfo->length : $currentProductInfo->length_cm,
                    'width' => customer()->isUSA() ? $currentProductInfo->width : $currentProductInfo->width_cm,
                    'height' => customer()->isUSA() ? $currentProductInfo->height : $currentProductInfo->height_cm,
                    'weight' => customer()->isUSA() ? $currentProductInfo->weight : $currentProductInfo->weight_kg,
                ],
            ];
        }
        $information['product_type'] = $productInfo;
        $information['associated_product_ids'] = $associateProducts ?: [];

        //上架中的商品和未上架的商品分开获取数据
        if (in_array($productDetail['status'], ProductStatus::notSale())) {
            [$productImages, $materialImages, $materialManualsFiles, $materialVideos, $designs, $isOrignDesign] =
                $this->getNotOnSaleForAuditData($productId, $productDetail);
        } else {
            [$productImages, $materialImages, $materialManualsFiles, $materialVideos, $designs, $isOrignDesign] =
                $this->getOnSaleForAuditData($productId, $productDetail, $currentProductInfo);
        }

        //产品描述和退货政策
        $materialPackage = [
            'product_images' => $productImages,
            'images' => $materialImages ?? [],
            'files' => $materialManualsFiles ?? [],
            'videos' => $materialVideos ?: [],
            'designs' => $designs ?: [],
            'certification_documents' => $currentProductInfo->certificationDocuments()->selectRaw('url, name, type_id')->get()->toArray(),
        ];

        ProductAudit::query()
            ->where('product_id', $productId)
            ->where('audit_type', ProductAuditType::PRODUCT_INFO)
            ->where('status', ProductAuditStatus::PENDING)
            ->update(['is_delete' => 1]);

        //退返品+描述
        $returnWarrantyData = [
            'return_warranty' => json_decode($currentProductInfo->description->return_warranty, true),
            'return_warranty_text' => $currentProductInfo->description->return_warranty_text,
            'description' => $productDetail['Product Description'] ?: $currentProductInfo->description->description,
        ];

        $productAuditId = ProductAudit::query()->insertGetId([
            'product_id' => $productId,
            'customer_id' => customer()->getId(),
            'audit_type' => 1,
            'category_id' => $categoryId,
            'information' => json_encode($information),
            'description' => json_encode($returnWarrantyData),
            'material_package' => json_encode($materialPackage),
            'is_original_design' => $isOrignDesign,
            'assemble_info' => json_encode($assembleInfo),
        ]);
        if ($productAuditId) {
            Product::query()->where('product_id', $productId)->update(['product_audit_id' => $productAuditId]);
        }

        return $productId;
    }

    /**
     * 获取未上架状态下，审核所需数据
     * @param int $productId
     * @param array $productDetail
     * @return array
     */
    private function getNotOnSaleForAuditData(int $productId, array $productDetail)
    {
        $productImages = ProductImage::query()->where('product_id', $productId)
            ->orderBy('sort_order')
            ->get()
            ->map(function ($item) {
                return ['url' => $item->image, 'sort' => $item->sort_order];
            })->toArray();

        $materialImages = ProductPackageImage::query()->where('product_id', $productId)
            ->get()
            ->map(function ($item) {
                return ['url' => $item->image, 'name' => $item->image_name, 'file_id' => $item->product_package_image_id, 'm_id' => 0];
            })->toArray();

        $materialManualsFiles = ProductPackageFile::query()->where('product_id', $productId)
            ->get()
            ->map(function ($item) {
                return ['url' => $item->file, 'name' => $item->file_name, 'file_id' => $item->product_package_file_id, 'm_id' => 0];
            })->toArray();

        $materialVideos = ProductPackageVideo::query()->where('product_id', $productId)
            ->get()
            ->map(function ($item) {
                return ['url' => $item->video, 'name' => $item->video_name, 'file_id' => $item->product_package_video_id, 'm_id' => 0];
            })->toArray();

        //原创标记
        $isOrignDesign = 0;
        if (!empty($productDetail['Original Design'])) {
            $isOrignDesign = strtolower($productDetail['Original Design']) == 'yes' ? 1 : 0;
        } else {
            $productExtInfo = ProductExts::query()->where('product_id', $productId)->first();
            if ($productExtInfo) {
                $isOrignDesign = $productExtInfo->is_original_design;
            }
        }
        //原创文件
        $designs = [];
        if (isset($productDetail['Original Design']) && !empty($productDetail['Original Design'])) {
            if (strtolower($productDetail['Original Design']) == 'yes') {
                $designs = db('oc_product_package_original_design_image')->where('product_id', $productId)
                    ->get()
                    ->map(function ($item) {
                        return [
                            'url' => $item->image,
                            'name' => $item->image_name,
                            'file_id' => $item->product_package_original_design_image_id,
                            'm_id' => 0
                        ];
                    })->toArray();
            }
        }

        return [$productImages, $materialImages, $materialManualsFiles, $materialVideos, $designs, $isOrignDesign];
    }

    /**
     * 获取未上架状态下，审核所需数据
     * @param int $productId
     * @param array $productDetail
     * @param Product $currentProductInfo
     * @return array
     */
    private function getOnSaleForAuditData(int $productId, array $productDetail, Product $currentProductInfo)
    {
        $mainImageUrl = $productDetail['Main Image Path'][0]['file_real_path'] ?? '';
        $sort = 1;
        //分4种情况 11 10 01 00  主图和display image是有关联的
        if ($productDetail['Images Path(to be displayed)'] && $mainImageUrl) {
            $productImages = array_map(function ($item) use (&$sort, $mainImageUrl) {
                if (StorageCloud::image()->getRelativePath($mainImageUrl) != StorageCloud::image()->getRelativePath($item['file_real_path'])) {
                    return [
                        'url' => StorageCloud::image()->getRelativePath($item['file_real_path']),
                        'sort' => $sort++
                    ];
                }
                return [];
            }, $productDetail['Images Path(to be displayed)']);
            $mainImageArr = [
                'url' => StorageCloud::image()->getRelativePath($mainImageUrl),
                'sort' => 0,
            ];
            $productImages = array_filter($productImages);
            array_unshift($productImages, $mainImageArr);
        } elseif ($productDetail['Images Path(to be displayed)']) {
            $tempMainImageUrl = $currentProductInfo->image ? StorageCloud::image()->getRelativePath($currentProductInfo->image) : '';
            $productImages = array_map(function ($item) use (&$sort, $tempMainImageUrl) {
                if ($tempMainImageUrl && $tempMainImageUrl != StorageCloud::image()->getRelativePath($item['file_real_path'])) {
                    return [
                        'url' => StorageCloud::image()->getRelativePath($item['file_real_path']),
                        'sort' => $sort++
                    ];
                }
                return [];
            }, $productDetail['Images Path(to be displayed)']);
            $productImages = array_filter($productImages);
            if ($tempMainImageUrl) {
                $mainImageArr = [
                    'url' => $tempMainImageUrl,
                    'sort' => 0,
                ];
                array_unshift($productImages, $mainImageArr);
            }
        } elseif ($mainImageUrl) {
            $productImages = ProductImage::query()->where('product_id', $productId)
                ->orderBy('sort_order')
                ->get()
                ->map(function ($item) {
                    return [
                        'url' => $item->image,
                        'sort' => $item->sort_order
                    ];
                })->toArray();
            $mainImageArr = [
                'url' => StorageCloud::image()->getRelativePath($mainImageUrl),
                'sort' => 0,
            ];
            array_unshift($productImages, $mainImageArr);
        } else {
            //正常是包含了主图
            $productImages = ProductImage::query()->where('product_id', $productId)
                ->orderBy('sort_order')
                ->get()
                ->map(function ($item) {
                    return [
                        'url' => $item->image,
                        'sort' => $item->sort_order
                    ];
                })->toArray();
        }

        if (!empty($productDetail['Images Path(other material)'])) {
            $materialImages = array_map(function ($item) {
                $originName = substr($item['file_origin_path'], (strrpos($item['file_origin_path'], '/') ?: -1) + 1);
                return [
                    'url' => StorageCloud::image()->getRelativePath($item['file_real_path']),
                    'name' => $originName,
                    'file_id' => 0,
                    'm_id' => 0
                ];
            }, $productDetail['Images Path(other material)']);
        } else {
            $materialImages = ProductPackageImage::query()->where('product_id', $productId)
                ->get()
                ->map(function ($item) {
                    return [
                        'url' => $item->image,
                        'name' => $item->image_name,
                        'file_id' => $item->product_package_image_id,
                        'm_id' => 0
                    ];
                })->toArray();
        }

        if (!empty($productDetail['Material Manual Path'])) {
            $materialManualsFiles = array_map(function ($item) {
                $originName = substr($item['file_origin_path'], (strrpos($item['file_origin_path'], '/') ?: -1) + 1);
                return [
                    'url' => StorageCloud::image()->getRelativePath($item['file_real_path']),
                    'name' => $originName,
                    'file_id' => 0,
                    'm_id' => 0
                ];
            }, $productDetail['Material Manual Path']);
        } else {
            $materialManualsFiles = ProductPackageFile::query()->where('product_id', $productId)
                ->get()
                ->map(function ($item) {
                    return [
                        'url' => $item->file,
                        'name' => $item->file_name,
                        'file_id' => $item->product_package_file_id,
                        'm_id' => 0
                    ];
                })->toArray();
        }

        if (!empty($productDetail['Material Video Path'])) {
            $materialVideos = array_map(function ($item) {
                $originName = substr($item['file_origin_path'], (strrpos($item['file_origin_path'], '/') ?: -1) + 1);
                return [
                    'url' => StorageCloud::image()->getRelativePath($item['file_real_path']),
                    'name' => $originName,
                    'file_id' => 0,
                    'm_id' => 0
                ];
            }, $productDetail['Material Video Path']);
        } else {
            $materialVideos = ProductPackageVideo::query()->where('product_id', $productId)
                ->get()
                ->map(function ($item) {
                    return [
                        'url' => $item->video,
                        'name' => $item->video_name,
                        'file_id' => $item->product_package_video_id,
                        'm_id' => 0
                    ];
                })->toArray();
        }

        //原创
        $isOrignDesign = 0;
        $isOrignDesignFillInExcel = 0;
        if (!empty($productDetail['Original Design'])) {
            $isOrignDesign = strtolower($productDetail['Original Design']) == 'yes' ? 1 : 0;
            $isOrignDesignFillInExcel = 1;
        } else {
            $productExtInfo = ProductExts::query()->where('product_id', $productId)->first();
            if ($productExtInfo) {
                $isOrignDesign = $productExtInfo->is_original_design;
            }
        }

        $designs = [];
        if ($isOrignDesignFillInExcel == 1) {
            if ($isOrignDesign == 1) {
                if ($productDetail['Supporting Files Path']) {
                    $designs = array_map(function ($item) {
                        $originName = substr($item['file_origin_path'], (strrpos($item['file_origin_path'], '/') ?: -1) + 1);
                        return [
                            'url' => StorageCloud::image()->getRelativePath($item['file_real_path']),
                            'name' => $originName,
                            'file_id' => 0,
                            'm_id' => 0
                        ];
                    }, $productDetail['Supporting Files Path'] ?? []);
                }
            }
        } else {
            $designs = db('oc_product_package_original_design_image')->where('product_id', $productId)
                ->get()
                ->map(function ($item) {
                    return [
                        'url' => $item->image,
                        'name' => $item->image_name,
                        'file_id' => $item->product_package_original_design_image_id,
                        'm_id' => 0
                    ];
                })->toArray();
        }

        return [$productImages, $materialImages, $materialManualsFiles, $materialVideos, $designs, $isOrignDesign];
    }
}
