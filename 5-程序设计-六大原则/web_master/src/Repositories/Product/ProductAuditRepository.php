<?php

namespace App\Repositories\Product;

use App\Components\Storage\StorageCloud;
use App\Enums\Common\YesNoEnum;
use App\Enums\Product\ProductAuditStatus;
use App\Enums\Product\ProductAuditType;
use App\Enums\Product\ProductCustomizeType;
use App\Enums\Product\ProductStatus;
use App\Models\CustomerPartner\CustomerPartnerToCustomer;
use App\Models\Product\Product;
use App\Models\Product\ProductAudit;
use App\Models\Product\ProductCertificationDocumentType;
use App\Models\Product\ProductCustomField;
use App\Repositories\Product\Option\OptionValueRepository;
use App\Services\Product\ProductService;
use Cart\Currency;
use App\Repositories\Seller\SellerRepository;
use Framework\App;

class ProductAuditRepository
{
    /** @var Currency */
    private $currency;

    public function __construct()
    {
        $this->currency = app('registry')->get('currency');
    }

    /**
     * 获取一条产品审核记录
     * @param int $id 即oc_product_audit表主键
     * @param int $sellerId 即oc_customer表主键
     * @return ProductAudit
     */
    public function getProductAuditByIdAndCustomerId(int $id, int $sellerId)
    {
        return ProductAudit::query()->where('id', $id)->where('customer_id', $sellerId)->first();
    }

    /**
     * 获取店铺退返品标准,获取不到从平台配置获取
     * @return array|mixed
     */
    public function getStoreReturnWarranty()
    {
        $storeInfo = CustomerPartnerToCustomer::query()->where('customer_id', customer()->getId())->first();
        if ($storeInfo && $storeInfo->return_warranty) {
            return json_decode($storeInfo->return_warranty, true);
        }
        //从配置获取
        return app(SellerRepository::class)->getDefaultReturnWarranty();
    }

    /**
     * 获取商品类型，这儿不是商品的字段product_type，而是商品新增/编辑页面里面的自定义的product_type，
     *
     * @param int $comboFlag
     * @param int $partFlag
     * @return int
     */
    public function getProductTypeWithProductInfo($comboFlag, $partFlag)
    {
        return $comboFlag == 1 ? ProductCustomizeType::PRODUCT_COMBO :
            ($partFlag == 1 ? ProductCustomizeType::PRODUCT_PART : ProductCustomizeType::PRODUCT_NORMAL);
    }


    /**
     * 获取商品审核信息
     * @param $auditId
     * @param $productId
     * @param $isPreview 是否预览，预览某些逻辑需要控制非上架的展示
     * @return array|bool
     * @throws \Exception
     * @see ProductRepository::getSellerProductInfo()
     */
    public function getSellerProductAuditInfo($auditId, $productId, $isPreview = false)
    {
        $countryId = customer()->getCountryId();
        $precision = AMERICAN_COUNTRY_ID == $countryId ? 2 : 0;
        $auditInfo = ProductAudit::query()->alias('pa')
            ->where([
                ['id', '=', $auditId],
                ['is_delete', '=', YesNoEnum::NO]
            ])->first();
        if (!$auditInfo || $auditInfo->product_id != $productId) {
            return false;
        }

        $auditInfoInformationArr = json_decode($auditInfo->information, true);
        $auditInfoDescriptionArr = json_decode($auditInfo->description, true);
        $auditInfoMaterialPackageArr = json_decode($auditInfo->material_package, true);
        $assembleInfo = json_decode($auditInfo->assemble_info, true);

        $productId = $auditInfo->product_id;
        $productInfo = Product::with('description')->find($productId);
        if (!$productInfo) {
            return false;
        }

        $auditRejectRemark = '';
        if ($auditInfo->status == ProductAuditStatus::NOT_APPROVED) {
            $auditRejectRemark = $auditInfo['remark'];
        }

        $colorOptionValueId = $auditInfoInformationArr['color_option_id'];
        $materialOptionValueId = $auditInfoInformationArr['material_option_id'];
        //颜色材质等信息
        $colorName = app(OptionValueRepository::class)->getNameByOptionValueId($colorOptionValueId) ?? '';
        $materialName = app(OptionValueRepository::class)->getNameByOptionValueId($materialOptionValueId) ?? '';

        //分组信息
        $groupInfos = $auditInfoInformationArr['group_id'];

        //类目信息
        $productCategory = app(CategoryRepository::class)->getUpperCategory($auditInfo->category_id, [], false);
        /** @var \ModelToolImage $modelToolImage */
        $modelToolImage = load()->model('tool/image');

        //关联商品 子商品(只预览上架的产品)
        $associatedProductIds = Product::query()->alias('p')
            ->whereIn('p.product_id', $auditInfoInformationArr['associated_product_ids'])
            ->where('p.product_id', '<>', $productId)
            ->when($isPreview, function ($q) {$q->where('p.status', '=', ProductStatus::ON_SALE);})
            ->where('p.is_deleted', YesNoEnum::NO)
            ->pluck('p.product_id')
            ->toArray();


        $resultsAssociate = [];
        $associateProducts = [];
        if ($associatedProductIds) {
            $resultsAssociate = app(ProductOptionRepository::class)->getOptionByProductIds($associatedProductIds);
        }

        $customFieldData = app(ProductRepository::class)->getCustomFieldByIds(array_column($resultsAssociate, 'product_id'));
        $fillerData = app(ProductRepository::class)->getProductExtByIds(array_column($resultsAssociate, 'product_id'));

        foreach ($resultsAssociate as $key => $value) {
            $tmp = [
                'sku' => $value['sku'],
                'mpn' => $value['mpn'],
                'image' => $modelToolImage->resize($value['image'], 50, 50),
                'name' => $value['name'],
                'product_id' => $value['product_id'],
                'color' => $value['color_name'],
                'material' => $value['material_name'],
                'custom_field' => $customFieldData[$value['product_id']] ?? [],
                'filler' => $fillerData[$value['product_id']]['filler_option_value']['name'] ?? null

            ];
            $associateProducts[] = $tmp;
        }

        /** @var \ModelAccountCustomerpartner $mace */
        $mace = load()->model('account/customerpartner');
        // 子产品
        $auditComboQty = [];//["product_id"=>"qty"]
        $auditComboProductIds = [];
        foreach ($auditInfoInformationArr['product_type']['combo'] as $key => $value) {
            $auditComboProductIds[] = $value['product_id'];
            $auditComboQty[$value['product_id']] = $value['quantity'];
        }

        $comboProducts = $mace->getComboProductBySubProductIds($auditComboProductIds);
        array_walk($comboProducts, function (&$item) use ($modelToolImage, $auditComboQty) {
            $item['length'] = customer()->isUSA() ? $item['length'] : $item['length_cm'];
            $item['width'] = customer()->isUSA() ? $item['width'] : $item['width_cm'];
            $item['height'] = customer()->isUSA() ? $item['height'] : $item['height_cm'];
            $item['weight'] = customer()->isUSA() ? $item['weight'] : $item['weight_kg'];
            $item['image'] = $modelToolImage->resize($item['image'], 50, 50);
            $item['qty'] = $auditComboQty[$item['product_id']];
            $item['quantity'] = $auditComboQty[$item['product_id']];
        });

        //ltl判断
        $isLtl = db('oc_product_to_tag')->where('product_id', $productId)->where('tag_id', (int)configDB('tag_id_oversize'))->exists();

        $materialImages = [];
        foreach ($auditInfoMaterialPackageArr['images'] as $key => $value) {
            $tmp = [
                'orig_url' => $value['url'],
                'm_id' => $value['m_id'],
                'file_id' => $value['file_id'],
                'name' => $value['name'],
            ];
            app(ProductService::class)->resolveImageItem($tmp, true);
            $materialImages[] = $tmp;
        }

        $originalDesign = [];
        if (!empty($auditInfoMaterialPackageArr['designs'])) {
            foreach ($auditInfoMaterialPackageArr['designs'] as $key => $value) {
                $tmp = [
                    'orig_url' => $value['url'],
                    'm_id' => $value['m_id'],
                    'file_id' => $value['file_id'],
                    'name' => $value['name'],
                ];
                app(ProductService::class)->resolveImageItem($tmp, true);
                $originalDesign[] = $tmp;
            }
        }

        $materialManuals = [];
        foreach ($auditInfoMaterialPackageArr['files'] as $key => $value) {
            $tmp = [
                'orig_url' => $value['url'],
                'm_id' => $value['m_id'],
                'file_id' => $value['file_id'],
                'name' => $value['name'],
            ];
            app(ProductService::class)->resolveImageItem($tmp, true);
            $materialManuals[] = $tmp;
        }

        $materialVideo = [];
        foreach ($auditInfoMaterialPackageArr['videos'] as $key => $value) {
            $tmp = [
                'orig_url' => $value['url'],
                'm_id' => $value['m_id'],
                'file_id' => $value['file_id'],
                'name' => $value['name'],
            ];
            app(ProductService::class)->resolveImageItem($tmp, true);
            $materialVideo[] = $tmp;
        }

        $productImages = [];
        foreach ($auditInfoMaterialPackageArr['product_images'] as $key => $value) {
            $tmp = [
                'orig_url' => $value['url'],
                'sort_order' => $value['sort']++,
            ];
            app(ProductService::class)->resolveImageItem($tmp);
            $productImages[] = $tmp;
        }
        if (count($productImages) > 1) {
            $sort_order = array_column($productImages, 'sort_order');
            array_multisort($sort_order, SORT_ASC, $productImages);
        }

        $certificationDocuments = [];
        $certificationDocumentTypeNameMap = ProductCertificationDocumentType::queryRead()->get()->pluck('title', 'id');
        if (!empty($auditInfoMaterialPackageArr['certification_documents'])) {
            foreach ($auditInfoMaterialPackageArr['certification_documents'] as $value) {
                $value['orig_url'] = $value['url'];
                $value['thumb'] = StorageCloud::image()->getUrl($value['url'], ['check-exist' => false]);
                $value['url'] = StorageCloud::image()->getUrl($value['url'], ['check-exist' => false]);
                $value['type_name'] = $certificationDocumentTypeNameMap->get($value['type_id'], '');
                $certificationDocuments[] = $value;
            }
        }

        $return_warranty = ($auditInfoDescriptionArr['return_warranty']) ? $auditInfoDescriptionArr['return_warranty'] : (object)[];

        $result = [
            'edit_type' => 'read',
            'audit_id' => (int)$auditId,
            'auditRejectRemark' => $auditRejectRemark,
            'price_display' => $auditInfoInformationArr['display_price'],
            'quantity_display' => $productInfo->quantity_display,
            'product_id' => $auditInfo->product_id,
            'name' => $auditInfoInformationArr['title'],
            'description' => $auditInfoDescriptionArr['description'],
            'price' => ($auditInfo->audit_type == ProductAuditType::PRODUCT_PRICE) ? ($auditInfo->price) : ($auditInfoInformationArr['current_price']),
            'sku' => $productInfo->sku,
            'mpn' => $productInfo->mpn,
            'quantity' => $productInfo->quantity,
            'image' => $auditInfoInformationArr['image'],
            'image_show_url' => $modelToolImage->resize($auditInfoInformationArr['image'], 100, 100),
            'weight' => isset($auditInfoInformationArr['product_type']['no_combo']['weight']) ? $auditInfoInformationArr['product_type']['no_combo']['weight'] : '',
            'length' => isset($auditInfoInformationArr['product_type']['no_combo']['length']) ? $auditInfoInformationArr['product_type']['no_combo']['length'] : '',
            'width' => isset($auditInfoInformationArr['product_type']['no_combo']['width']) ? $auditInfoInformationArr['product_type']['no_combo']['width'] : '',
            'height' => isset($auditInfoInformationArr['product_type']['no_combo']['height']) ? $auditInfoInformationArr['product_type']['no_combo']['height'] : '',
            'status' => $productInfo->status,
            'color' => $colorOptionValueId,
            'color_name' => $colorName,
            'material' => $materialOptionValueId,
            'material_name' => $materialName,
            'combo_flag' => $productInfo->combo_flag,
            'buyer_flag' => $auditInfoInformationArr['sold_separately'],
            'part_flag' => $productInfo->part_flag,
            'product_group_ids' => empty($groupInfos) ? '' : implode(',', $groupInfos),
            'product_category' => $productCategory,
            'product_associated' => $associateProducts,
            'combo' => $comboProducts,
            'product_type' => app(ProductAuditRepository::class)->getProductTypeWithProductInfo($productInfo->combo_flag, $productInfo->part_flag), //这里的prudcut_type并不是商品属性product_type
            'is_ltl' => $isLtl ? 1 : 0,
            'material_images' => $materialImages,
            'original_design' => $originalDesign,
            'original_product'=> empty($originalDesign) ? 0 : 1,
            'material_manuals' => $materialManuals,
            'material_video' => $materialVideo,
            'product_image' => $productImages,
            'return_warranty' => $return_warranty,//退返协议
            'return_warranty_text' => $auditInfoDescriptionArr['return_warranty_text'] ?? '',//修改产品后，用于Seller预览
            'associated_product_ids' => $associatedProductIds,//修改产品后，用于Seller预览
            'preview_category_id' => $auditInfo->category_id,//修改产品后，用于Seller预览’
            'non_sellable_on' => $auditInfoInformationArr['non_sellable_on'] ?? '',
            'upc' => $auditInfoInformationArr['upc'] ?? '',
            'is_customize' => $auditInfoInformationArr['is_customize'] ?? null,
            'origin_place_code' => $auditInfoInformationArr['origin_place_code'] ?? '',
            'filler' => $auditInfoInformationArr['filler'] ?: 0,
            'information_custom_field' => $auditInfoInformationArr['custom_field'] ?? [],
            'assemble_length' => $assembleInfo['assemble_length'] ?? '',
            'assemble_width' => $assembleInfo['assemble_width'] ?? '',
            'assemble_height' => $assembleInfo['assemble_height'] ?? '',
            'assemble_weight' => $assembleInfo['assemble_weight'] ?? '',
            'dimensions_custom_field' => $assembleInfo['custom_field'] ?? [],
            'certification_documents' => $certificationDocuments,
        ];

        return $result;
    }

    /**
     * 显示预览按钮
     * @param int $auditType ProductAuditType
     * @return int
     */
    public function isShowPreviewButton($auditType)
    {
        return ($auditType == ProductAuditType::PRODUCT_INFO) ? (1) : (0);
    }

    /**
     * 显示修改商品信息的按钮
     * @param int $auditType ProductAuditType::PRODUCT_INFO | ProductAuditType::PRODUCT_PRICE
     * @param int $auditStatus 审核状态 ProductAuditStatus
     * @return int
     */
    public function isShowInformationButton($auditType, $auditStatus)
    {
        $isShowInformationButton = 0;//显示修改商品信息的按钮
        if ($auditType == ProductAuditType::PRODUCT_INFO && in_array($auditStatus, [ProductAuditStatus::PENDING, ProductAuditStatus::NOT_APPROVED])) {
            $isShowInformationButton = 1;
        }
        return $isShowInformationButton;
    }

    /**
     * 显示删除按钮
     * @param int $auditStatus 审核状态 ProductAuditStatus
     * @return int
     */
    public function isShowCancelButton($auditStatus)
    {
        return ($auditStatus == ProductAuditStatus::PENDING) ? 1 : 0;
    }
}
