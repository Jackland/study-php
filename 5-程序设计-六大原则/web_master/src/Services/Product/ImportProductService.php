<?php

namespace App\Services\Product;

use App\Catalog\Forms\Product\Import\InsertValidate;
use App\Components\BatchInsert;
use App\Components\Storage\StorageCloud;
use App\Enums\Common\YesNoEnum;
use App\Enums\Product\ProductImportType;
use App\Enums\Product\ProductStatus;
use App\Helper\ProductHelper;
use App\Logging\Logger;
use App\Models\Customer\Country;
use App\Models\CustomerPartner\CustomerPartnerToCustomer;
use App\Models\Link\CustomerPartnerToProduct;
use App\Models\Link\ProductToCategory;
use App\Models\Link\ProductToTag;
use App\Models\Log\ProductLtlLog;
use App\Models\Product\Option\Option;
use App\Models\Product\Option\SellerPriceHistory;
use App\Models\Product\Product;
use App\Models\Product\ProductAssociate;
use App\Models\Product\ProductDescription;
use App\Models\Product\ProductExts;
use App\Models\Product\ProductImportBatch;
use App\Models\Product\ProductImportBatchErrorReport;
use App\Models\Product\ProductSetInfo;
use App\Repositories\Product\CategoryRepository;
use App\Repositories\Product\ProductOptionRepository;
use App\Repositories\Product\ProductRepository;
use App\Services\Store\StoreSelectedCategoryService;
use Carbon\Carbon;

class ImportProductService
{
    /**
     * 添加导入的产品
     * @param array $products
     * @param int $customerId
     * @param int $country
     * @param string $fileName
     * @return array
     */
    public function addImportProducts(array $products, int $customerId, int $country, string $fileName): array
    {
        /** @var ProductOptionRepository $productOptionRepository */
        $productOptionRepository = app(ProductOptionRepository::class);
        $colorOptionIdNameMap = array_map('strtolower', $productOptionRepository->getOptionsById(Option::COLOR_OPTION_ID)->pluck('name', 'option_value_id')->toArray());
        $materialOptionIdNameMap = array_map('strtolower', $productOptionRepository->getOptionsById(Option::MATERIAL_OPTION_ID)->pluck('name', 'option_value_id')->toArray());

        // 通过和错误的产品区分
        $correctProducts = [];
        $errorProducts = [];
        $importFormValidate = new InsertValidate($products, $customerId, $country, $colorOptionIdNameMap, $materialOptionIdNameMap);

        foreach ($products as $product) {
            $analysisedProduct = $importFormValidate->validate($product);
            //写入错误日志还得用老数据写入
            $product['Old Supporting Files Path'] = $product['Supporting Files Path'];
            $product['Old Main Image Path'] = $product['Main Image Path'];
            $product['Old Images Path(to be displayed)'] = $product['Images Path(to be displayed)'];
            $product['Old Images Path(other material)'] = $product['Images Path(other material)'];
            $product['Old Material Manual Path'] = $product['Material Manual Path'];
            $product['Old Material Video Path'] = $product['Material Video Path'];

            if ($analysisedProduct['can_insert'] == 1) {
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
                $errorProducts[] = $product; //不能导入的不校验文件之类的，这个无须merge
            }
        }

        // 处理添加产品
        $screenName = CustomerPartnerToCustomer::query()->where('customer_id', $customerId)->value('screenname');

        $idProductMap = [];
        $ltlProductIds = [];
        $addNocomboProductIds = [];
        foreach ($correctProducts as $correctProduct) {
            $correctProduct['color_option_id'] = array_search(strtolower($correctProduct['Color']), $colorOptionIdNameMap);
            $correctProduct['material_option_id'] = array_search(strtolower($correctProduct['Material']), $materialOptionIdNameMap);
            $correctProduct['filler_option_id'] = array_search(strtolower($correctProduct['Filler']), $materialOptionIdNameMap);
            $correctProduct['country_code'] = $correctProduct['Place of Origin'] ? array_search(strtolower($correctProduct['Place of Origin']), array_map('strtolower', Country::getCodeNameMap())) : '';
            try {
                dbTransaction(function () use ($correctProduct, $customerId, $screenName, $country, &$idProductMap, &$ltlProductIds, &$addNocomboProductIds) {
                    $correctProduct['MPN'] = strtoupper($correctProduct['MPN']);
                    $correctProduct['Sub-items'] = !empty($correctProduct['Sub-items']) ? strtoupper($correctProduct['Sub-items']) : '';
                    list($result, $newAddNoComboProductIds) = $this->addProduct($correctProduct, $customerId, $screenName, $country);
                    $idProductMap = $idProductMap + $result;
                    $addNocomboProductIds = array_merge($addNocomboProductIds, $newAddNoComboProductIds);

                    // 处理ltl超大件提醒
                    /** @var Product $checkLtlProduct */
                    $checkLtlProduct = end($result);
                    if ($country == AMERICAN_COUNTRY_ID && !customer()->isInnerAccount() && ProductHelper::getProductLtlRemindLevel($checkLtlProduct->width, $checkLtlProduct->length, $checkLtlProduct->height, $checkLtlProduct->weight) == 1) {
                        $ltlProductIds[] = $checkLtlProduct->product_id;
                    }
                });
            } catch (\Throwable $e) {
                Logger::importProducts('批量导入添加某个产品失败:' . $e->getMessage(), 'error');
                Logger::importProducts('msg', 'error', [
                    Logger::CONTEXT_VAR_DUMPER => ['batch_add_product_error' => $e],
                ]);

                // 插入失败加入错误列表
                $errorProducts[] = $correctProduct;
            }
        }

        // 处理运费接口
        if ($idProductMap) {
            app(ProductService::class)->updateProductsFreight($idProductMap);
        }

        // 处理美国产品信息同步
        if ($addNocomboProductIds) {
            if ($country == AMERICAN_COUNTRY_ID && !customer()->isInnerAccount()) {
                try {
                    ProductHelper::sendSyncProductsRequest(array_unique($addNocomboProductIds));
                } catch (\Throwable $e) {
                    Logger::syncProducts($e->getMessage());
                }
            }
        }

        // 插入导入批次表
        $productImportBatch = new ProductImportBatch();
        $productImportBatch->customer_id = $customerId;
        $productImportBatch->file_name = $fileName;
        $productImportBatch->type = ProductImportType::BATCH_INSERT;
        $productImportBatch->product_count = count($products);
        $productImportBatch->product_error_count = count($errorProducts);
        $productImportBatch->create_time = Carbon::now();
        $productImportBatch->save();

        // 添加某个导入批次的错误数据
        $this->insertProductImportBatchErrorReport($errorProducts, $customerId, $productImportBatch->id);

        return [$ltlProductIds, $productImportBatch];
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
                        'category_id' => $errorProduct['Category ID'],
                        'mpn' => $errorProduct['MPN'],
                        'sold_separately' => $errorProduct['Sold Separately'],
                        'not_sale_platform' => $errorProduct['Not available for sale on'],
                        'product_title' => $errorProduct['Product Title'],
                        'color' => $errorProduct['Color'],
                        'material' => $errorProduct['Material'],
                        'assemble_is_required' => '',
                        'product_size' => '',
                        'product_type' => $errorProduct['Product Type'],
                        'sub_items' => $errorProduct['Sub-items'],
                        'sub_items_quantity' => $errorProduct['Sub-items Quantity'],
                        'length' => $errorProduct['Length'],
                        'width' => $errorProduct['Width'],
                        'height' => $errorProduct['Height'],
                        'weight' => $errorProduct['Weight'],
                        'current_price' => $errorProduct['Current Price'],
                        'display_price' => $errorProduct['Display Price'],
                        'origin_design' => $errorProduct['Original Design'],
                        'description' => trim($errorProduct['Product Description']),
                        'error_content' => $errorProduct['error_content'] ?? 'insert product error',
                        'extends_info' => json_encode($extendsInfo),
                        'create_time' => $createTime,
                    ]);
                }
                $batchInsert->end();
            });
        } catch (\Throwable $e) {
            Logger::importProducts('批量导入错误报告添加失败:' . $e->getMessage(), 'error');
        }
    }

    /**
     * 添加产品
     * @param array $product
     * @param int $customerId
     * @param string $screenName
     * @param int $countryId
     * @return array
     */
    protected function addProduct(array $product, int $customerId, string $screenName, int $countryId): array
    {
        $newAddNoComboProductIds = [];
        $isCombo = strtolower($product['Product Type']) == 'combo item';
        $productInfo = app(ProductRepository::class)->getProductInfoByCustomerIdAndMpn($customerId, $product['MPN']);
        // 非combo的数据存储
        if (!$isCombo) {
            if (empty($productInfo)) {
                $productInfo = $this->addBasicsProductInfo(0, 0, ...func_get_args());
                $newAddNoComboProductIds[] = $productInfo->product_id;
            }
        } else {
            if (empty($productInfo)) {
                $productInfo = $this->addBasicsProductInfo(1, 0, ...func_get_args());
            }

            $subItemProductInfo = app(ProductRepository::class)->getProductInfoByCustomerIdAndMpn($customerId, $product['Sub-items']);
            if (empty($subItemProductInfo)) {
                $subItemProductInfo = $this->addBasicsProductInfo(0, 1, ...func_get_args());
                $newAddNoComboProductIds[] = $subItemProductInfo->product_id;
            }

            //插入 tb_sys_product_set_info
            ProductSetInfo::query()->insert([
                'set_mpn' => $product['Sub-items'],
                'mpn' => $product['MPN'],
                'weight' => $product['Weight'],
                'cubes' => 0,
                'height' => $product['Height'],
                'length' => $product['Length'],
                'width' => $product['Width'],
                'qty' => $product['Sub-items Quantity'],
                'product_id' => $productInfo->product_id,
                'set_product_id' => $subItemProductInfo->product_id,
                'seller_id' => $customerId,
            ]);

            // 当前的combo也需设置为ltl  内部seller产品B2B不再标记LTL
            if (!customer()->isInnerAccount() && ProductToTag::query()->isLTL($subItemProductInfo->product_id) && !ProductToTag::query()->isLTL($productInfo->product_id)) {
                ProductToTag::query()->insert($this->addProductToTagForLtl($productInfo->product_id));
            }
        }

        $idProductMap[$productInfo->product_id] = $productInfo;
        if (isset($subItemProductInfo)) {
            $idProductMap[$subItemProductInfo->product_id] = $subItemProductInfo;
        }

        return [$idProductMap, $newAddNoComboProductIds];
    }

    /**
     * 添加产品基础信息
     * @param bool $isCombo
     * @param bool $isSubItem
     * @param array $product
     * @param int $customerId
     * @param string $screenName
     * @param int $country
     * @return Product
     */
    private function addBasicsProductInfo(bool $isCombo, bool $isSubItem, array $product, int $customerId, string $screenName, int $country): Product
    {
        $isUSA = $country == AMERICAN_COUNTRY_ID;
        $productRepository = app(ProductRepository::class);

        $productModel = new Product();
        $productModel->combo_flag = $isCombo;
        $productModel->buyer_flag = $isSubItem ? 0 : strtolower($product['Sold Separately']) == 'yes';
        $productModel->model = $screenName;
        $productModel->part_flag = strtolower($product['Product Type']) == 'replacement part';
        $productModel->sku = $isSubItem ? $product['Sub-items'] : $product['MPN'];
        $productModel->mpn = $isSubItem ? $product['Sub-items'] : $product['MPN'];
        $productModel->upc = $isSubItem ? '' : $product['UPC'];
        $productModel->ean = '';
        $productModel->jan = '';
        $productModel->isbn = '';
        $productModel->asin = '';
        $productModel->location = '';
        $productModel->quantity = 0;
        $productModel->stock_status_id = 0;
        $productModel->manufacturer_id = 0;
        $productModel->shipping = 1;
        $productModel->points = 0;
        $productModel->tax_class_id = 0;
        $productModel->price = 0.00;
        $productModel->date_available = Carbon::now();
        $productModel->weight_class_id = 5;
        $productModel->weight = $isCombo ? '0.00' : ($isUSA ? $product['Weight'] : $productRepository->calculatePoundAndKg($product['Weight'], 2, 1));
        $productModel->length = $isCombo ? '0.00' : ($isUSA ? $product['Length'] : $productRepository->calculateInchesAndCm($product['Length'], 2, 1));
        $productModel->width = $isCombo ? '0.00' : ($isUSA ? $product['Width'] : $productRepository->calculateInchesAndCm($product['Width'], 2, 1));
        $productModel->height = $isCombo ? '0.00' : ($isUSA ? $product['Height'] : $productRepository->calculateInchesAndCm($product['Height'], 2, 1));
        $productModel->length_cm = $isCombo ? '0.00' : (!$isUSA ? $product['Length'] : $productRepository->calculateInchesAndCm($product['Length'], 1, 2));
        $productModel->width_cm = $isCombo ? '0.00' : (!$isUSA ? $product['Width'] : $productRepository->calculateInchesAndCm($product['Width'], 1, 2));
        $productModel->height_cm = $isCombo ? '0.00' : (!$isUSA ? $product['Height'] : $productRepository->calculateInchesAndCm($product['Height'], 1, 2));
        $productModel->weight_kg = $isCombo ? '0.00' : (!$isUSA ? $product['Weight'] : $productRepository->calculatePoundAndKg($product['Weight'], 1, 2));
        $productModel->length_class_id = 3;
        $productModel->subtract = 1;
        $productModel->minimum = 1;
        $productModel->sort_order = 1;
        $productModel->status = ProductStatus::WAIT_SALE;
        $productModel->viewed = 0;
        $productModel->date_added = Carbon::now();
        $productModel->date_modified = Carbon::now();
        $productModel->product_size = '';
        $productModel->price = $isSubItem ? 0 : $product['Current Price'];
        $productModel->price_display = $isSubItem ? 0 : strtolower($product['Display Price']) == 'visible';
        $productModel->need_install = 0;

        //主图 & 销售平台
        $productModel->image = '';
        $productModel->non_sellable_on = '';
        if (isset($product['Main Image Path'][0]['file_real_path'])) {
            $productModel->image = StorageCloud::image()->getRelativePath(trim($product['Main Image Path'][0]['file_real_path']));
        }
        //销售平台
        if (!empty($product['Not available for sale on'])) {
            $productModel->non_sellable_on = trim($product['Not available for sale on']);
        }

        $productModel->save();
        $productId = $productModel->product_id;

        // 插入关联
        ProductAssociate::query()->insert([
            'product_id' => $productId,
            'associate_product_id' => $productId,
        ]);

        // 添加配件标签关系
        if ($productModel->part_flag) {
            ProductToTag::query()->insert([
                'product_id' => $productId,
                'tag_id' => 2,
                'is_sync_tag' => 0,
                'create_user_name' => $customerId,
                'create_time' => Carbon::now(),
                'update_user_name' => $customerId,
                'program_code' => 'add product',
            ]);
        }

        // 添加combo标签关系
        if ($productModel->combo_flag) {
            ProductToTag::query()->insert([
                'product_id' => $productId,
                'tag_id' => 3,
                'is_sync_tag' => 0,
                'create_user_name' => $customerId,
                'create_time' => Carbon::now(),
                'update_user_name' => $customerId,
                'program_code' => 'add product',
            ]);
        }

        // 添加产品详情
        ProductDescription::query()->insert([
            'product_id' => $productId,
            'language_id' => 1,
            'name' => $isSubItem ? $product['Sub-items'] : $product['Product Title'],
            'description' => $product['Product Description'] ?? trim($product['Product Description']),
            'tag' => '',
            'meta_description' => '',
            'meta_keyword' => '',
        ]);

        // 弃用,依然插入数据
        db('oc_product_to_store')->insert(['product_id' => $productId, 'store_id' => 0]);

        // 添加产品类目
        ProductToCategory::query()->insert(['product_id' => $productId, 'category_id' => $product['Category ID']]);

        // 处理最近选择类目
        app(StoreSelectedCategoryService::class)->handleSelectedCategory($productId, $product['Category ID'], $customerId);

        // sku生成规则
        $productModel->sku = app(ProductRepository::class)->getProductSku($productModel->mpn, $isCombo);

        //添加ext 因为专利字段在ext
        ProductExts::query()->insert([
            'product_id' => $productId,
            'sku' => $productModel->sku,
            'is_original_design' => strtolower($product['Original Design']) == 'yes',
            'is_customize' => strtolower($product['Customized']) == 'yes',
            'origin_place_code' => $product['country_code'] ?: '',
            'filler' => $product['filler_option_id'] ?? 0,
            'assemble_length' => strtolower($product['Assembled Length']) == 'not applicable' ? -1.00 : $product['Assembled Length'],
            'assemble_width' => strtolower($product['Assembled Width']) == 'not applicable' ? -1.00 : $product['Assembled Width'],
            'assemble_height' => strtolower($product['Assembled Height']) == 'not applicable' ? -1.00 : $product['Assembled Height'],
            'assemble_weight' => strtolower($product['Assembled Weight']) == 'not applicable' ? -1.00 : $product['Assembled Weight'],
            'create_user_name' => 'b2b_batch_import', //留个标记
        ]);

        //专利图片
        if (!empty($product['Supporting Files Path'])) {
            app(ProductAttachmentService::class)->addProductSupportFiles($productId, $product['Supporting Files Path']);
        }
        //页面展示图片
        if (!empty($product['Images Path(to be displayed)']) || $productModel->image) {
            app(ProductAttachmentService::class)->addProductImages($productId, $product['Images Path(to be displayed)'] ?: [], $productModel->image);
        }
        //其它图片
        if (!empty($product['Images Path(other material)'])) {
            app(ProductAttachmentService::class)->addProductMaterialPackageImages($productId, $product['Images Path(other material)']);
        }
        //手册素材
        if (!empty($product['Material Manual Path'])) {
            app(ProductAttachmentService::class)->addProductMaterialManualsFile($productId, $product['Material Manual Path']);
        }
        //视频素材
        if (!empty($product['Material Video Path'])) {
            app(ProductAttachmentService::class)->addProductMaterialPackageVideo($productId, $product['Material Video Path']);
        }

        // 店铺关联产品
        CustomerPartnerToProduct::query()->insert([
            'customer_id' => $customerId,
            'product_id' => $productId,
            'price' => $isSubItem ? '0.00' : $product['Current Price'],
            'seller_price' => $isSubItem ? '0.00' : $product['Current Price'],
            'currency_code' => '',
            'quantity' => 0,
            'pickup_price' => 0,
        ]);

        // 增加产品价格记录
        SellerPriceHistory::query()->insert([
            'product_id' => $productId,
            'price' => $isSubItem ? '0.00' : $product['Current Price'],
            'add_date' => Carbon::now(),
            'status' => 1,
        ]);

        // 添加属性
        if (!$isSubItem) {
            if ($product['color_option_id']) {
                app(ProductOptionService::class)->insertProductOptionValue($productId, Option::COLOR_OPTION_ID, $product['color_option_id']);
            }

            if ($product['material_option_id']) {
                app(ProductOptionService::class)->insertProductOptionValue($productId, Option::MATERIAL_OPTION_ID, $product['material_option_id']);
            }
        }

        // 自动设置ltl 内部美国seller产品B2B不再标记LTL
        if ($productModel->combo_flag == YesNoEnum::NO && $country == AMERICAN_COUNTRY_ID && !customer()->isInnerAccount() && ProductHelper::getProductLtlRemindLevel($productModel->width, $productModel->length, $productModel->height, $productModel->weight) == 2) {
            $addProductToTags[] = $this->addProductToTagForLtl($productId);

            ProductLtlLog::query()->insert([
                'product_id' => $productId,
                'ltl_type' => ProductLtlLog::LTL_TYPE_SET,
                'length' => $productModel->length,
                'width' => $productModel->width,
                'height' => $productModel->height,
                'weight' => $productModel->weight,
                'remark' => '',
                'operator' => 'system',
                'create_time' => Carbon::now(),
            ]);

            // 当前的combo也需设置为ltl
            $comboProductIds = ProductSetInfo::query()->where('set_product_id', $productId)->get()->pluck('product_id')->unique()->toArray();
            if (!empty($comboProductIds)) {
                $existProductToTagProductIds = ProductToTag::query()->where('tag_id', 1)->whereIn('product_id', $comboProductIds)->get()->pluck('product_id')->toArray();
                $diffProductIds = array_diff($comboProductIds, $existProductToTagProductIds);
                foreach ($diffProductIds as $diffProductId) {
                    $addProductToTags[] = $this->addProductToTagForLtl($diffProductId);
                }
            }

            ProductToTag::query()->insert($addProductToTags);
        }

        $productModel->save();

        return $productModel;
    }

    /**
     * @param int $productId
     * @return array
     */
    private function addProductToTagForLtl($productId)
    {
        return [
            'product_id' => $productId,
            'tag_id' => 1,
            'is_sync_tag' => 0,
            'create_user_name' => 'system',
            'create_time' => Carbon::now(),
            'update_user_name' => NULL,
            'update_time' => NULL,
            'program_code' => 'add product',
        ];
    }

}
