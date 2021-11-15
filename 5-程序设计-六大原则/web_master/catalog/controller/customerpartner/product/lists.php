<?php

use App\Catalog\Controllers\AuthSellerController;
use App\Catalog\Search\CustomerPartner\Product\ProductListAuditSearch;
use App\Catalog\Search\CustomerPartner\Product\ProductListValidSearch;
use App\Enums\Common\YesNoEnum;
use App\Enums\Product\BuyFlag;
use App\Enums\Product\ComboFlag;
use App\Enums\Product\PriceDisplay;
use App\Enums\Product\ProductAuditStatus;
use App\Enums\Product\ProductAuditType;
use App\Enums\Product\ProductStatus;
use App\Enums\Product\ProductType;
use App\Helper\SummernoteHtmlEncodeHelper;
use App\Logging\Logger;
use App\Models\CustomerPartner\CustomerPartnerToCustomer;
use App\Models\Product\Option\SellerPrice;
use App\Models\Product\Product;
use App\Models\Product\ProductAudit;
use App\Repositories\Onsite\OnsiteFreightRepository;
use App\Repositories\Product\ProductAuditRepository;
use App\Repositories\Product\ProductRepository;
use App\Repositories\Product\ProductTagRepository;
use App\Repositories\Seller\SellerRepository;
use App\Services\Product\ProductAuditService;
use App\Services\Product\ProductListsService;
use App\Services\Product\ProductService;
use App\Services\Product\TemplateService;
use App\Widgets\ImageToolTipWidget;
use Carbon\Carbon;
use Framework\Exception\Exception;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Class ControllerCustomerpartnerAccountProductManagementLists
 * @property ModelAccountCustomerpartner $model_account_customerpartner
 * @property ModelAccountCustomerpartnerProductGroup $model_account_customerpartner_ProductGroup
 * @property ModelAccountCustomerpartnerProductList $model_account_customerpartner_productlist
 * @property ModelCatalogProduct $model_catalog_product
 * @property ModelCommonProduct $model_common_product
 * @property ModelCustomerPartnerDelicacyManagement $model_customerpartner_DelicacyManagement
 * @property ModelCustomerpartnerProductManage $model_customerpartner_product_manage
 * @property ModelToolImage $model_tool_image
 */
class ControllerCustomerpartnerProductLists extends AuthSellerController
{
    public $sellerId;
    public $precision;

    public function __construct(Registry $registry)
    {
        parent::__construct($registry);
        $this->sellerId = $this->customer->getId();
        $this->precision = $this->customer->getCountryId() == JAPAN_COUNTRY_ID ? 0 : 2;
    }

    protected function getBreadcrumbs($breadcrumbs = ['home', 'current'])
    {
        $breadcrumbs = [];
        $breadcrumbs[] = [
            'text' => __('产品管理',[],'catalog/document'),
            'href' => 'javascript:void(0);',
            'separator' => $this->language->get('text_separator')
        ];
        $breadcrumbs[] = [
            'text' => __('产品列表',[],'catalog/document'),
            'href' => $this->url->to(['customerpartner/product/lists/index']),
            'separator' => $this->language->get('text_separator')
        ];
        return $breadcrumbs;
    }

    /**
     * Seller 产品列表页
     * @return string
     */
    public function index()
    {
        $currency = $this->session->get('currency');
        $precision = intval($this->currency->getDecimalPlace($currency));//货币小数位数
        $data = [];
        $this->setDocumentInfo(__('产品列表',[],'catalog/document'));


        $this->document->addStyle('catalog/view/theme/default/stylesheet/MP/sell.css?v=' . APP_VERSION);

        $data['breadcrumbs'] = $this->getBreadcrumbs();
        $data['app_version'] = APP_VERSION;
        $data['tab'] = $this->request->get('tab', 'valid');
        $data['precision'] = $precision;

        return $this->render('customerpartner/product/lists_index', $data, [
            'separate_column_left' => 'account/customerpartner/column_left',
            'header' => 'account/customerpartner/header',
            'footer' => 'account/customerpartner/footer',
        ]);
    }

    /**
     * Seller 产品列表页 Valid Tab页
     * @return string
     * @throws Exception
     */
    public function valid()
    {
        $this->load->model('account/customerpartner/ProductGroup');
        $this->load->model('catalog/product');
        $this->load->model('common/product');
        $this->load->model('tool/image');
        $currency = $this->session->get('currency');
        $data = [];

        $this->request->query->set('filter_is_deleted', '0');
        $this->request->query->set('sort', 'p.product_id');
        $this->request->query->set('order', 'DESC');

        $search = new ProductListValidSearch($this->customer->getId());
        $dataProvider = $search->search($this->request->get());
        $results = $dataProvider->getList()->toArray();
        $total = $dataProvider->getTotalCount();
        $originalProductImage = $this->config->get('original_product_image');
        if ($total) {
            foreach ($results as $key => $result) {
                $tag_array = $this->model_catalog_product->getProductSpecificTag($result['product_id']);
                $tags = array();
                if (isset($tag_array)) {
                    foreach ($tag_array as $tag) {
                        if (isset($tag['origin_icon']) && !empty($tag['origin_icon'])) {
                            //采用唯一图片  LTL.jpg  part.jpg  combo-img.png
                            $img_url = $this->model_tool_image->getOriginImageProductTags($tag['origin_icon']);
                            $tags[] = '<img data-toggle="tooltip" class="' . $tag['class_style'] . '"  title="' . $tag['description'] . '" style="padding-left: 1px" src="' . $img_url . '">';
                        }
                    }
                }
                $results[$key]['originalImage'] = $result['is_original_design'] ? $originalProductImage:'';
                $results[$key]['isShowPriceButton'] = in_array($result['status'], ProductStatus::allSale());
                $results[$key]['isShowOnButton'] = in_array($result['status'], ProductStatus::notSale());
                $results[$key]['isShowOffButton'] = in_array($result['status'], ProductStatus::onSale());
                $results[$key]['isShowLTLButton'] = app(ProductTagRepository::class)->isShowLTLOrExpressButton($this->customer->getCountryId(), $result, 'ltl');
                $results[$key]['isShowExpressButton'] = app(ProductTagRepository::class)->isShowLTLOrExpressButton($this->customer->getCountryId(), $result, 'express');
                $results[$key]['show_combo_flag'] = ComboFlag::getDescription($result['combo_flag']);
                $results[$key]['show_buyer_flag'] = BuyFlag::getDescription($result['buyer_flag']);
                $results[$key]['show_price_display'] = PriceDisplay::getDescription($result['price_display']);
                $results[$key]['show_status'] = ProductStatus::getDescription($result['status']);
                $results[$key]['show_package_fee_d'] = isset($result['package_fee_d']) ? $this->currency->formatCurrencyPrice($result['package_fee_d'], $currency) : 'N/A';
                $results[$key]['show_package_fee_h'] = isset($result['package_fee_h']) ? $this->currency->formatCurrencyPrice($result['package_fee_h'], $currency) : 'N/A';
                $results[$key]['show_shipping_fee'] = $this->currency->format(round((float)$result['freight'], $this->precision), $currency);
                $results[$key]['show_price'] = $this->currency->format($result['price'], $currency);
                $results[$key]['show_new_price'] = isset($result['new_price']) ? $this->currency->format($result['new_price'], $currency) : '';
                if ($result['audit_price']) {
                    $results[$key]['show_new_price'] = $this->currency->format($result['audit_price'], $currency);
                    $results[$key]['new_price'] = $result['audit_price'];
                    $results[$key]['effect_time'] = $result['audit_price_effect_time'];
                }
                if (empty($results[$key]['new_price'])) {
                    $results[$key]['new_price'] = $result['price'];
                }
                $results[$key]['new_price'] = customer()->isJapan() ? round($results[$key]['new_price']) : $results[$key]['new_price'];
                $results[$key]['thumb'] = $this->model_tool_image->resize($result['image'], 40, 40);
                $results[$key]['tag'] = $tags;
                $results[$key]['alarm_price'] = $this->model_common_product->getAlarmPrice($result['product_id'], true, [
                    'freight' => $result['freight'],
                    'peak_season_surcharge' => $result['peak_season_surcharge'],
                    'danger_fee' => $result['danger_fee'],
                ]);
                // 计算危险品重量  此处是为了判断ltl取消，危险品重量判断， combo品没有ltl设置操作
                $results[$key]['danger_weight'] = ($result['danger_flag'] && !$result['combo_flag']) ? $result['weight'] : 0;
            }
        }

        $data['total'] = $total; // 总计
        $data['products'] = $results;  // 列表
        $data['paginator'] = $dataProvider->getPaginator();
        $data['sort'] = $dataProvider->getSort();
        $data['order'] = (in_array(mb_strtolower($this->request->get('order')), [null, '', 'asc'])) ? ('desc') : ('asc');
        $data['search'] = $search->getSearchData();
        $data['page_limit'] = request()->get('page_limit', $search->defaultPageSize);


        $data['product_groups'] = $this->model_account_customerpartner_ProductGroup->getAllListAndNoPage([], $this->customer->getId());
        // 商品上下架状态修改
        $data['product_status'] = ProductStatus::getViewItems();
        $data['product_combos'] = ComboFlag::getViewItems();
        $data['product_buyFlags'] = BuyFlag::getViewItems();
        //统计热区
        $countWait = $search->getCountWait();
        $countOn = $search->getCountOn();
        $countOff = $search->getCountOff();
        $data['countWait'] = $countWait;
        $data['countOn'] = $countOn;
        $data['countOff'] = $countOff;
        $data['currency'] = $this->session->get('currency');
        $data['symbolLeft'] = $symbolLeft = $this->currency->getSymbolLeft($currency);
        $data['symbolRight'] = $symbolRight = $this->currency->getSymbolRight($currency);
        $data['currency_symbol'] = $symbolLeft . $symbolRight;
        // 是否显示云送仓提醒
        $data['is_show_cwf_notice'] = app(SellerRepository::class)->isShowCwfNotice();
        $data['is_japan'] = $this->customer->isJapan();
        $data['is_usa'] = $this->customer->isUSA();
        $data['is_not_inner_account'] = !$this->customer->isInnerAccount();
        // 当前seller的时区
        $toTz = $this->customer->isUSA() ? TENSE_TIME_ZONES_NO[getPSTOrPDTFromDate(date('Y-m-d H:i:s'))] : COUNTRY_TIME_ZONES_NO[$this->session->get('country', 'GBR')];
        $data['seller_time_zones_no'] = explode(':', $toTz)[0];
        $data['is_europe'] = customer()->isEurope() ? 1 : 0;

        return $this->render('customerpartner/product/lists_valid', $data);
    }

    /**
     * Seller 产品列表页 invalid Tab页
     * @return string
     */
    public function invalid()
    {
        $this->load->model('account/customerpartner/ProductGroup');
        $this->load->model('catalog/product');
        $this->load->model('tool/image');
        $currency = $this->session->get('currency');
        $data = [];
        $this->request->query->set('filter_is_deleted', '1');
        $this->request->query->set('sort', 'p.date_modified');
        $this->request->query->set('order', 'DESC');


        $search = new ProductListValidSearch($this->customer->getId());
        $dataProvider = $search->search($this->request->get());
        $results = $dataProvider->getList()->toArray();
        $total = $dataProvider->getTotalCount();
        $originalProductImage = $this->config->get('original_product_image');
        if ($total) {
            foreach ($results as $key => $result) {
                $tag_array = $this->model_catalog_product->getProductSpecificTag($result['product_id']);
                $tags = array();
                if (isset($tag_array)) {
                    foreach ($tag_array as $tag) {
                        if (isset($tag['origin_icon']) && !empty($tag['origin_icon'])) {
                            //采用唯一图片  LTL.jpg  part.jpg  combo-img.png
                            $img_url = $this->model_tool_image->getOriginImageProductTags($tag['origin_icon']);
                            $tags[] = '<img data-toggle="tooltip" class="' . $tag['class_style'] . '"  title="' . $tag['description'] . '" style="padding-left: 1px" src="' . $img_url . '">';
                        }
                    }
                }

                $results[$key]['originalImage'] = $result['is_original_design'] ? $originalProductImage:'';
                $results[$key]['show_combo_flag'] = ComboFlag::getDescription($result['combo_flag']);
                $results[$key]['show_buyer_flag'] = BuyFlag::getDescription($result['buyer_flag']);
                $results[$key]['show_price_display'] = PriceDisplay::getDescription($result['price_display']);
                $results[$key]['show_status'] = ProductStatus::getDescription($result['status']);
                $results[$key]['show_package_fee_d'] = isset($result['package_fee_d']) ? $this->currency->formatCurrencyPrice($result['package_fee_d'], $currency) : 'N/A';
                $results[$key]['show_package_fee_h'] = isset($result['package_fee_h']) ? $this->currency->formatCurrencyPrice($result['package_fee_h'], $currency) : 'N/A';
                $results[$key]['show_shipping_fee'] = $this->currency->format(round((float)$result['freight'], $this->precision), $currency);
                $results[$key]['show_price'] = $this->currency->format($result['price'], $currency);
                $results[$key]['show_new_price'] = isset($result['new_price']) ? $this->currency->format($result['new_price'], $currency) : '';
                $results[$key]['thumb'] = $this->model_tool_image->resize($result['image'], 40, 40);
                $results[$key]['tag'] = $tags;
            }
        }


        $data['total'] = $total; // 总计
        $data['products'] = $results;  // 列表
        $data['paginator'] = $dataProvider->getPaginator();
        $data['sort'] = $dataProvider->getSort();
        $data['order'] = (in_array(mb_strtolower($this->request->get('order')), [null, '', 'asc'])) ? ('desc') : ('asc');
        $data['search'] = $search->getSearchData();
        $data['page_limit'] = request()->get('page_limit', $search->defaultPageSize);


        $data['product_groups'] = $this->model_account_customerpartner_ProductGroup->getAllListAndNoPage([], $this->customer->getId());
        // 商品上下架状态修改
        $data['product_status'] = ProductStatus::getViewItems();
        $data['product_combos'] = ComboFlag::getViewItems();
        $data['product_buyFlags'] = BuyFlag::getViewItems();
        //统计热区
        $countWait = $search->getCountWait();
        $countOn = $search->getCountOn();
        $countOff = $search->getCountOff();
        $data['countWait'] = $countWait;
        $data['countOn'] = $countOn;
        $data['countOff'] = $countOff;
        $data['currency'] = $this->session->get('currency');
        $data['symbolLeft'] = $symbolLeft = $this->currency->getSymbolLeft($currency);
        $data['symbolRight'] = $symbolRight = $this->currency->getSymbolRight($currency);
        $data['currency_symbol'] = $symbolLeft . $symbolRight;
        return $this->render('customerpartner/product/lists_invalid', $data);
    }

    /**
     * Seller 产品列表页 审核记录 Tab页
     * @return string
     */
    public function audit()
    {
        $this->load->model('account/customerpartner/ProductGroup');
        $this->load->model('catalog/product');
        $this->load->model('tool/image');
        $currency = $this->session->get('currency');
        $data = [];

        $this->request->query->set('filter_is_deleted', '0');
        $this->request->query->set('sort', 'pa.id');
        $this->request->query->set('order', 'DESC');
        $originalProductImage = $this->config->get('original_product_image');

        $search = new ProductListAuditSearch($this->customer->getId());
        $dataProvider = $search->search($this->request->get());
        $results = $dataProvider->getList()->toArray();
        $total = $dataProvider->getTotalCount();
        if ($total) {
            $nowDate = Carbon::now()->toDateTimeString();
            foreach ($results as $key => $result) {
                $tag_array = $this->model_catalog_product->getProductSpecificTag($result['product_id']);
                $tags = array();
                if (isset($tag_array)) {
                    foreach ($tag_array as $tag) {
                        if (isset($tag['origin_icon']) && !empty($tag['origin_icon'])) {
                            //采用唯一图片  LTL.jpg  part.jpg  combo-img.png
                            $img_url = $this->model_tool_image->getOriginImageProductTags($tag['origin_icon']);
                            $tags[] = '<img data-toggle="tooltip" class="' . $tag['class_style'] . '"  title="' . $tag['description'] . '" style="padding-left: 1px" src="' . $img_url . '">';
                        }
                    }
                }
                $results[$key]['originalImage'] = $result['is_original_design'] ? $originalProductImage:'';
                $show_audit_price = '';
                $price_effect_time = '';//生效之前显示Seller填写的内容，生效之后清空
                if ($result['audit_type'] == ProductAuditType::PRODUCT_PRICE) {
                    if (
                        $result['sp_status'] == 2 &&
                        $result['audit_price'] == $result['sp_new_price'] &&
                        $nowDate >= $result['price_effect_time']
                    ) {
                        $show_audit_price = '';
                        $price_effect_time = '';
                    } else {
                        $show_audit_price = $this->currency->format($result['audit_price'], $currency);
                        $price_effect_time = $result['price_effect_time'];
                    }
                }

                $results[$key]['show_remark'] = SummernoteHtmlEncodeHelper::encode($result['remark'], true);
                $results[$key]['isShowPreviewButton'] = app(ProductAuditRepository::class)->isShowPreviewButton($result['audit_type']);
                $results[$key]['isShowInformationButton'] = app(ProductAuditRepository::class)->isShowInformationButton($result['audit_type'], $result['audit_status']);//显示修改商品信息的按钮
                $results[$key]['isShowCancelButton'] = app(ProductAuditRepository::class)->isShowCancelButton($result['audit_status']);
                $results[$key]['show_product_status'] = ProductStatus::getDescription($result['product_status']);
                $results[$key]['show_audit_status'] = ProductAuditStatus::getDescription($result['audit_status']);
                $results[$key]['show_current_price'] = $this->currency->format($result['current_price'], $currency);
                $results[$key]['show_audit_price'] = $show_audit_price;
                $results[$key]['price_effect_time'] = $price_effect_time;
                $results[$key]['show_audit_type'] = ProductAuditType::getDescription($result['audit_type']);
                $results[$key]['thumb'] = $this->model_tool_image->resize($result['image'], 40, 40);
                $results[$key]['tag'] = $tags;
                $results[$key]['approved_time'] = $result['approved_time'] ?: '--';
            }
        }


        $data['total'] = $total; // 总计
        $data['products'] = $results;  // 列表
        $data['paginator'] = $dataProvider->getPaginator();
        $data['sort'] = $dataProvider->getSort();
        $data['order'] = (in_array(mb_strtolower($this->request->get('order')), [null, '', 'asc'])) ? ('desc') : ('asc');
        $data['search'] = $search->getSearchData();
        $data['page_limit'] = request()->get('page_limit', $search->defaultPageSize);


        $data['product_groups'] = $this->model_account_customerpartner_ProductGroup->getAllListAndNoPage([], $this->customer->getId());
        // 商品上下架状态修改
        $data['product_status'] = ProductStatus::getViewItems();
        $data['product_combos'] = ComboFlag::getViewItems();
        $data['product_buyFlags'] = BuyFlag::getViewItems();
        $data['product_audit_status'] = ProductAuditStatus::getViewItems();
        //统计热区
        $countProcess = $search->getCountAuditProcess();
        $countApproved = $search->getCountAuditApproved();
        $countNotApproved = $search->getCountAuditNotApproved();
        $countCancel = $search->getCountAuditCancel();
        $data['countProcess'] = $countProcess;
        $data['countApproved'] = $countApproved;
        $data['countNotApproved'] = $countNotApproved;
        $data['countCancel'] = $countCancel;
        $data['currency'] = $this->session->get('currency');
        $data['symbolLeft'] = $symbolLeft = $this->currency->getSymbolLeft($currency);
        $data['symbolRight'] = $symbolRight = $this->currency->getSymbolRight($currency);
        $data['currency_symbol'] = $symbolLeft . $symbolRight;
        return $this->render('customerpartner/product/lists_audit', $data);
    }

    public function downloadValid()
    {
        $this->request->query->set('filter_is_deleted', '0');
        $this->request->query->set('sort', 'p.product_id');
        $this->request->query->set('order', 'DESC');

        $search = new ProductListValidSearch($this->customer->getId());
        $dataProvider = $search->search($this->request->get(), true);
        $list = $dataProvider->getList()->toArray();

        app(TemplateService::class)->downloadValid($list);
    }

    public function downloadInvalid()
    {
        $this->request->query->set('filter_is_deleted', '1');
        $this->request->query->set('sort', 'p.date_modified');
        $this->request->query->set('order', 'DESC');

        $search = new ProductListValidSearch($this->customer->getId());
        $dataProvider = $search->search($this->request->get(), true);
        $list = $dataProvider->getList()->toArray();
        //$total = $dataProvider->getTotalCount();

        app(TemplateService::class)->downloadInvalid($list);
    }

    public function downloadAudit()
    {
        $this->request->query->set('filter_is_deleted', '0');
        $this->request->query->set('sort', 'pa.id');
        $this->request->query->set('order', 'DESC');

        $search = new ProductListAuditSearch($this->customer->getId());
        $dataProvider = $search->search($this->request->get(), true);
        $list = $dataProvider->getList()->toArray();
        //$total = $dataProvider->getTotalCount();
        app(TemplateService::class)->downloadAudit($list);
    }

//region 软删除
    public function soft_delete()
    {
        $this->response->headers->set('Content-Type', 'application/json');
        $this->setLanguages('account/customerpartner/productlist');
        $this->setLanguages('account/customerpartner/addproduct');
        $this->load->model('account/customerpartner');
        $this->load->model('account/customerpartner/ProductGroup');
        /** @var ModelAccountCustomerpartner $model_account_customerpartner */
        $model_account_customerpartner = $this->model_account_customerpartner;

        // 14086 库存订阅列表中的产品上下架提醒
        $customer_id = $this->customer->getId();

        $selectedProductIds = $this->request->post('id', []);
        if (!is_array($selectedProductIds)) {
            $selectedProductIds = (array)$selectedProductIds;
        }
        // 默认包含子商品
        $includeCombo = (int)($this->request->post('is_delete_sub', 0));
        // 是否为批量
        $isSingleFlag = (int)($this->request->post('is_single'));


        if (!$selectedProductIds) {
            return $this->jsonFailed();
        }
        if (!in_array($includeCombo, [YesNoEnum::NO, YesNoEnum::YES])) {
            return $this->jsonFailed();
        }


        //删除item时，需要校验该产品是否作为subitem存在于combo的关联中；是 则给予提示，终止操作。
        $parentSkuArr = app(ProductRepository::class)->getParentSkuByProductIds($selectedProductIds);
        foreach ($parentSkuArr as $parentProduct) {
            $setSku = reset($parentProduct)['set_sku'];;
            $parentSkus = implode(',', array_column($parentProduct, 'sku'));
            return $this->jsonFailed(__('删除失败！该item(:setSku)属于combo item(:parentSkus)的sub-item，若要删除，请先删除combo item(:parentSkus)', ['setSku'=>$setSku, 'parentSkus'=>$parentSkus], 'controller/product'));
        }


        //场景：Combo品C1，有子产品PA、PB；
        //     Combo品C2，有子产品PA、PC；
        //删除comboC1时选择要删除子产品，因为PA存在于C2的combo关系中，处理逻辑是C1和PB删除成功，PA删除失败，
        //提示是：C1 & PB deleted successfully！PA failed to delete! This item belongs to the sub-item of combo item xxxx, if you want to delete, please delete the combo item first.
        $canNotSkuArr=[];
        if ($includeCombo) {
            $allParent = app(ProductRepository::class)->getAllParentByParentProductIds($selectedProductIds);
            $allProductIds = $allParent['allProductIds'];
            $canNotSkuArr = $allParent['canNotSkuArr'];
            $subProdcutIdArr = array_keys($allParent['canIdSkuArr']);
            $selectedProductIds = array_merge($selectedProductIds, $subProdcutIdArr);
        } else {
            $allProductIds = $selectedProductIds;
        }
        $idSkuArr = Product::select(['product_id', 'sku'])->whereIn('product_id', $allProductIds)->get()->pluck('sku', 'product_id')->toArray();//一维数组 product_id=>sku

        // 删除product 同时从product group中删除
        $this->model_account_customerpartner_ProductGroup->linkDeleteByProducts($this->sellerId, $selectedProductIds);
        $updateRows = $model_account_customerpartner->setProductIsDeleted($selectedProductIds, $customer_id, false);//第一个参数已包含所有能删除的产品，所以第三个参数是false
        if (!$updateRows) {
            $code = 0;
            $msg = $this->language->get(!$isSingleFlag ? 'text_batch_delete_fail' : 'text_single_delete_fail');
        } else {
            $code = 200;
            $canSkuArr = [];
            foreach ($selectedProductIds as $productId) {
                $canSkuArr[] = $idSkuArr[$productId];
            }
            $strSKU = implode(' & ', array_values($canSkuArr));
            $strDeleted = __(':sku 删除成功！', ['sku' => $strSKU], 'controller/product');
            $strFailedDetail = '';
            foreach ($canNotSkuArr as $subSku => $otherSkus) {
                $strFailedDetail .= (__(':sku 删除失败！该item属于combo item(:parentSkus)的sub-item，若要删除，请先删除combo item(:parentSkus)', ['sku' => $subSku, 'parentSkus' => $otherSkus], 'controller/product') . ',<br>');
            }
            $strFailedEnd = __('若要删除，请先删除combo item', [], 'controller/product');
            if ($strFailedDetail) {
                $code = 201;
                $msg = $strDeleted . '<br><br>' . $strFailedDetail . '<br>' . $strFailedEnd;
            } else {
                $msg = $strDeleted;
            }
        }

        // 14086 库存订阅列表中的产品上下架提醒
        // 软删除产品  $selectedProductIds ["14327","14329"]
        // 0 下架
        foreach ($selectedProductIds as $key => $value) {
            $model_account_customerpartner->sendProductionInfoToBuyer($value, $customer_id, 0);
        }

        return $this->jsonSuccess([], $msg, $code);
    }


    /**
     * 批量恢复
     * @role Seller
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @throws Exception
     */
    public function batch_recover()
    {
        $this->response->headers->set('Content-Type', 'application/json');

        $selectedProductIds = $this->request->input->get('id', []);
        // 是否为批量
        $isSingleFlag = (int)($this->request->input->get('is_single'));

        try {
            dbTransaction(function () use ($selectedProductIds) {
                $ret = app(ProductListsService::class)->setProductIsNotDeleted($selectedProductIds, $this->sellerId);
                if ($ret['ret'] !== 1) {
                    throw new Exception($ret['msg']);
                }
            });
        } catch (Throwable $e) {
            return $this->jsonFailed($e->getMessage());
        }

        return $this->jsonSuccess([], __('产品已经恢复成为有效产品！', [], 'controller/product'));
    }
//endregion

    /**
     * 上架产品
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function onShelfProductAction()
    {
        $productId = request()->post('product_id', '');
        if (empty($productId)) {
            return $this->jsonFailed(__('产品上架失败！', [], 'controller/product'));
        }

        try {
            $result = app(ProductAuditService::class)->saveProductInfoAudit($productId, $this->sellerId, customer()->getCountryId());
        } catch (Exception $e) {
            switch ($e->getCode()) {
                case 403:
                    return $this->jsonFailed(__('产品信息不全，无法提交上架申请，请编辑补充完整产品信息后再次提交审核！', [], 'controller/product'));
                case 400:
                    return $this->jsonFailed(__('产品已经上架，请勿重复操作！', [], 'controller/product'), [], 400);
                case 405:
                    return $this->jsonFailed(__('账户可用资产金额小于0，暂不支持上架产品', [], 'controller/seller_asset'), [], 'controller/product');
                case 406:
                    return $this->jsonFailed(__('您还没有运费报价，产品无法提交审核', [], 'controller/product'), []);
                case 407:
                    return $this->jsonFailed(__('您还没有LTL运费报价，产品无法提交审核', [], 'controller/product'), []);
                default:
                    return $this->jsonFailed(__('产品上架失败！', [], 'controller/product'));
            }
        }
        if ($result === 'success') { //区分处理不可单独售卖商品，此类商品可直接上架
            return $this->jsonSuccess([], __('对于不可单独售卖的产品，申请上架不需要审核，产品状态已经更新为已上架！', [], 'controller/product'));
        }

        return $this->jsonSuccess([], __('产品将提交审核，审核通过之后产品状态为已上架！', [], 'controller/product'));
    }

    /**
     * 批量上架接口
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function batchOnShelfProduct()
    {
        $productId = request()->post('product_ids', '');
        if (empty($productId)) {
            return $this->jsonFailed(__('请先选择数据！', [], 'controller/product'));
        }
        $productIds = explode(',', $productId);
        $products = app(ProductRepository::class)->getProductByIds($productIds, ['product_id', 'mpn'], ['description' => function($query) {
            $query->select(['product_id', 'return_warranty', 'return_warranty_text']);
        }])->toArray();
        foreach ($products as $product) {
            if (empty($product['description']) || empty($product['description']['return_warranty']) || empty($product['description']['return_warranty_text'])) {
                return $this->jsonFailed('error');
            }
        }

        $undone = 0;
        $done = 0;
        foreach ($productIds as $id) {
            try {
                app(ProductAuditService::class)->saveProductInfoAudit($id, $this->sellerId, customer()->getCountryId());
                $done++;
            } catch (Exception $e) {
                switch ($e->getCode()) {
                    case 400:
                        $done++; // 对于已经上架的商品, 不记录为失败
                    break;
                    default:
                        $undone++;
                }
            }
        }
        if ($undone) {
            return $this->jsonSuccess([], __('有X条产品信息不完善，无法申请上架，其他产品已申请上架，等待平台审核', ['num' => $undone], 'controller/product'));
        }

        return $this->jsonSuccess([], __('X条产品申请上架成功，等待平台审核', ['num' => $done], 'controller/product'));
    }

    /**
     * 给商品添加 退返品信息
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function addReturnWarranty()
    {
        $productId = request()->post('product_ids', '');
        $returnWarranty = request()->post('return_warranty', '');
        $returnWarrantyTEXT = request()->post('return_warranty_text', '');
        $returnWarranty = str_replace('&quot;', '"', $returnWarranty);
        $returnWarrantyTEXT = SummernoteHtmlEncodeHelper::decode($returnWarrantyTEXT, true);
        if (empty($productId) || empty($returnWarranty) || empty($returnWarrantyTEXT)) {
            return $this->jsonFailed(__('请先选择数据！', [], 'controller/product'));
        }
        $productIds = explode(',', $productId);
        $products = app(ProductRepository::class)->getProductByIds($productIds, ['product_id']);
        foreach ($products as $product) {
            $product->description()->where('product_id', $product->product_id)->update([
                'return_warranty' => $returnWarranty,
                'return_warranty_text' => $returnWarrantyTEXT,
            ]);
        }
        return $this->jsonSuccess();
    }

    /**
     * get 请求
     * check商品是否未设置退返品信息
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function checkReturnWarranty()
    {
        $productId = request()->get('product_ids', '');
        if (empty($productId)) {
            return $this->jsonFailed(__('请先选择数据！', [], 'controller/product'));
        }

        $productIds = explode(',', $productId);
        $products = app(ProductRepository::class)->getProductByIds($productIds, ['product_id', 'mpn'], ['description' => function($query) {
            $query->select(['product_id', 'return_warranty', 'return_warranty_text']);
        }])->toArray();
        $missReturnWarranty = [];
        foreach ($products as $product) {
            if (empty($product['description']) || empty($product['description']['return_warranty']) || empty($product['description']['return_warranty_text'])) {
                array_push($missReturnWarranty, [
                    'product_id' => $product['product_id'],
                    'mpn' => $product['mpn']
                ]);
            }
        }
        // 校验全部通过, 没有 未设置退返品信息的商品
        if (empty($missReturnWarranty)) {
            return $this->jsonSuccess();
        }
        $customerPartner = CustomerPartnerToCustomer::where(['customer_id' => $this->customer->getId()])->first();
        if (empty($customerPartner) || empty($customerPartner->return_warranty)) { // 店铺未设置则取平台默认的退返品规则
            $returnWarranty = app(SellerRepository::class)->getDefaultReturnWarranty();
        } else {
            $returnWarranty = json_decode($customerPartner->return_warranty, true);
        }

        return $this->jsonFailed('failed', ['products' => $missReturnWarranty, 'return_warranty' => $returnWarranty]);
    }

    /**
     * 下架产品前的提醒
     * @return JsonResponse
     * @throws Exception
     */
    public function offShelfProductActionBeforeReminds()
    {
        $productId = request()->post('product_id', '');
        if (empty($productId)) {
            return $this->jsonSuccess();
        }

        $product = Product::query()->find($productId);
        if (empty($product) || $product->customerPartnerToProduct->customer_id != $this->sellerId) {
            return $this->jsonSuccess();
        }

        $associatedProductIds = $product->associatesProducts()->where('associate_product_id', '!=', $productId)->pluck('associate_product_id')->toArray();
        // 返点模板提醒
        /** @var ModelAccountCustomerpartnerRebates $modelAccountCustomerPartnerRebates */
        $modelAccountCustomerPartnerRebates = load()->model('account/customerpartner/rebates');
        $tplNumList = $modelAccountCustomerPartnerRebates->get_rebate_all_count($associatedProductIds);

        // 精细化价格提醒
        $existDelicacyManagement = db('oc_delicacy_management')->where('seller_id', $this->sellerId)->where('product_id', $productId)->exists();

        if ($tplNumList && $existDelicacyManagement) {
            return $this->jsonSuccess([
                'msg' => __('您已经将此产品设置为下架，如果您要从返点模板（专有价格）中删除产品，请修改返点模板（专有价格）。<br/>但是，它不会影响现行协议。', [], 'controller/product'),
            ]);
        }
        if ($tplNumList) {
            return $this->jsonSuccess([
                'msg' => __('您已经将此产品设置为下架，如果您要从返点模板中删除产品，请修改返点模板。<br/>但是，它不会影响现行协议。', [], 'controller/product'),
            ]);
        }
        if ($existDelicacyManagement) {
            return $this->jsonSuccess([
                'msg' => __('您已经将此产品设置为下架，如果您要从专有价格中删除产品，请修改专有价格。<br/>但是，它不会影响现行协议。', [], 'controller/product'),
            ]);
        }

        return $this->jsonSuccess();
    }

    /**
     * 下架产品
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @throws Exception
     */
    public function offShelfProductAction()
    {
        $productId = request()->post('product_id', '');
        if (empty($productId)) {
            return $this->jsonFailed(__('产品下架失败！', [], 'controller/product'));
        }

        try {
            app(ProductListsService::class)->setProductStatusOff($productId, $this->sellerId);
        } catch (Exception $e) {
            if ($e->getCode() == 400) {//产品已经下架，请勿重复操作
                return $this->jsonFailed(__('产品已经下架，请勿重复操作！', [], 'controller/product'), [], 400);
            }
            return $this->jsonFailed(__('产品下架失败！', [], 'controller/product'));
        }

        // 发送站内信给buyer
        /** @var ModelAccountCustomerpartner $model */
        $model = load()->model('account/customerpartner');
        $model->sendProductionInfoToBuyer($productId, $this->sellerId, 0);

        return $this->jsonSuccess([], __('产品下架成功，产品状态为已下架！', [], 'controller/product'));
    }

    /**
     * 批量下架
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @throws \Exception
     */
    public function batchOffShelfProduct()
    {
        $productId = request()->post('product_ids', '');
        if (empty($productId)) {
            return $this->jsonFailed(__('请先选择数据！', [], 'controller/product'));
        }
        $productIds = explode(',', $productId);
        $productIds = app(ProductListsService::class)->batchSetProductStatusOff($productIds, customer()->getId());

        // 发送站内信给buyer
        /** @var ModelAccountCustomerpartner $model */
        $model = load()->model('account/customerpartner');
        foreach ($productIds as $id) {
            $model->sendProductionInfoToBuyer($id, $this->sellerId, 0);
        }
        return $this->jsonSuccess([], __('批量下架成功', [], 'controller/product'));
    }

    /**
     * LTL弹窗提示 支持批量
     * @role Seller
     * @return string
     */
    public function ltlWindow()
    {
        if (!customer()->isUSA() || customer()->isInnerAccount()) {
            return $this->render('customerpartner/product/lists_ltlWindow', ['lists' => []]);
        }

        $product_ids = $this->request->get('product_ids', '');
        $showCheckBox = $this->request->get('show_checkbox', false);
        $productIds = [];
        if ($product_ids) {
            $productIds = explode(',', $product_ids);
        }
        $lengthWithHeightWeight = [
            'length' => $this->request->get('length', 0),
            'width' => $this->request->get('width', 0),
            'height' => $this->request->get('height', 0),
            'weight' => $this->request->get('weight', 0),
            'mpn' => $this->request->get('mpn'),
        ];
        $someLtlRemindProducts = app(ProductTagRepository::class)->getSomeLtlRemindProducts($productIds, $this->sellerId, $lengthWithHeightWeight);

        $freightQuoteDetail = db('tb_freight_quote_category as c')
            ->join('tb_freight_version as v', 'c.version_id', '=', 'v.id')
            ->selectRaw('c.*')
            ->where('v.status', 1)
            ->where('c.type', 1)
            ->first();

        $overSpecificationQuote = db('tb_freight_quote_detail')->where('quote_category_id', $freightQuoteDetail->id)
            ->where('code', 'OVER_SPECIFICATION_QUOTE')
            ->value('value');

        return $this->render('customerpartner/product/lists_ltlWindow', [
            'lists' => $someLtlRemindProducts,
            'show_checkbox' => $showCheckBox,
            'isExpress' => $this->request->get('isExpress', 0),
            'over_specification_quote' => $overSpecificationQuote,
        ]);
    }

    /**
     * 设置为LTL发货的产品
     * 单商品操作
     * @role Seller
     */
    public function ltlSave()
    {
        if (!customer()->isUSA() || customer()->isInnerAccount()) {
            return $this->jsonSuccess([], __('设置为LTL发货成功！', [], 'controller/product'));
        }

        $product_ids = $this->request->post('product_ids', '');
        $productIds = explode(',', $product_ids);

        if (customer()->isGigaOnsiteSeller()) {
            $freightInfo = app(OnsiteFreightRepository::class)->calculateOnsiteFreightInfo(customer()->getId());
            if (!$freightInfo['ltl_quote']) {
                return $this->jsonFailed(__('您还没有LTL运费报价，无法转为LTL产品！', [], 'controller/product'));
            }
        }

        $someLtlRemindProducts = app(ProductTagRepository::class)->getSomeLtlRemindProducts($productIds, $this->sellerId);

        if (empty($someLtlRemindProducts)) {
            return $this->jsonSuccess([], __('设置为LTL发货成功！', [], 'controller/product'));
        }

        app(ProductService::class)->setProductsLtlTag($someLtlRemindProducts, $this->sellerId, customer()->getFirstName() . customer()->getLastName(), customer()->getCountryId());

        return $this->jsonSuccess([], __('设置为LTL发货成功！', [], 'controller/product'));
    }

    /**
     * 取消ltl
     * @return JsonResponse
     * @throws Exception
     */
    public function ltlCancel()
    {
        if (!customer()->isUSA() || customer()->isInnerAccount()) {
            return $this->jsonSuccess([], __('设置为快递发货成功！', [], 'controller/product'));
        }

        $product_ids = $this->request->post('product_ids', '');
        $productIds = explode(',', $product_ids);
        $someLtlRemindProducts = app(ProductTagRepository::class)->getSomeLtlRemindProducts($productIds, $this->sellerId);

        if (empty($someLtlRemindProducts)) {
            return $this->jsonSuccess([], __('设置为快递发货成功！', [], 'controller/product'));
        }

        foreach ($someLtlRemindProducts as $someLtlRemindProduct) {
            /** @var Product $someLtlRemindProduct */
            if (!$someLtlRemindProduct->danger_flag || $someLtlRemindProduct->combo_flag) {
                continue;
            }
            if ($someLtlRemindProduct->weight >= 70) {
                return $this->jsonSuccess([], __('LTL危险品重量超过70磅，无法切换成 非LTL产品，如有疑问请联系平台客服。', [], 'catalog/view/customerpartner/product/lists_index'));
            }
        }

        app(ProductService::class)->cancelProductsLtlTag($someLtlRemindProducts, customer()->getFirstName() . customer()->getLastName(), customer()->getCountryId());

        return $this->jsonSuccess([], __('设置为快递发货成功！', [], 'controller/product'));
    }

    /**
     * 修改价格
     * @return JsonResponse
     */
    public function modify()
    {
        $productId = request()->post('product_id', 0);
        $modifyPrice = request()->post('modify_price', '');
        $effectTime = request()->post('effect_time', '');
        $priceDisplay = request()->post('price_display', '');
        if (empty($productId)) {
            return $this->jsonFailed();
        }
        if (!empty($modifyPrice) && !preg_match('/^(\d{1,7})$/', $modifyPrice) && customer()->isJapan()) {
            return $this->jsonFailed(__('价格只能是0到9999999之间的数字。', [], 'controller/product'));
        }
        if (!empty($modifyPrice) && !preg_match('/^(\d{1,7})(\.\d{0,2})?$/', $modifyPrice) && !customer()->isJapan()) {
            return $this->jsonFailed(__('价格只能是0.00到9999999.99之间的数字。', [], 'controller/product'));
        }

        /** @var Product $product */
        $product = Product::query()->find($productId);

        //加个校验，欧洲国别的补运费产品禁止修改价格
        if ($product->product_type == ProductType::COMPENSATION_FREIGHT && customer()->isEurope()) {
            return $this->jsonFailed(__('欧洲补运费商品不能修改价格！', [], 'controller/product'));
        }

        // 未修改价格不需要验证时间
        if ($modifyPrice == '') {
            goto end;
        }

        // 待上架的产品不需要验证时间
        if ($product->status == ProductStatus::WAIT_SALE) {
            goto end;
        }

        // 降价的产品不需要验证时间（填写时间，需要判断格式）
        if ($modifyPrice < $product->price) {
            if (!empty($effectTime) && !preg_match('/^[1-9]\d{3}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])\s+(20|21|22|23|[0-1]\d)(:[0-5]\d:[0-5]\d)?$/', $effectTime)) {
                return $this->jsonFailed(__('生效时间格式不正确！', [], 'controller/product'));
            }
            if (!empty($effectTime)) {
                $effectTime = date('Y-m-d H:00:00', strtotime(analyze_time_string($effectTime)));
                if (Carbon::now()->format('Y-m-d H:i:s') > $effectTime) {
                    return $this->jsonFailed(__('生效时间不能早于当前时间！', [], 'controller/product'));
                }
            }

            goto end;
        }

        // 价格未发生变化
        if ($modifyPrice == $product->price) {
            goto end;
        }

        // 展示的价格和日期未发生变动
        $sellerPrice = SellerPrice::query()->where('product_id', $product->product_id)->first();
        $showEffectTime = !empty($sellerPrice->effect_time) ? $sellerPrice->effect_time->toDateTimeString() : '';
        $showPrice = !empty($sellerPrice->new_price) ? $sellerPrice->new_price : '';
        /** @var ProductAudit $productAudit */
        $productAudit = ProductAudit::query()->where('is_delete', YesNoEnum::NO)->where('status', ProductAuditStatus::PENDING)->find($product->price_audit_id);
        $showEffectTime = !empty($productAudit->price_effect_time) ? $productAudit->price_effect_time->toDateTimeString() : $showEffectTime;
        $showPrice = !empty($productAudit->price) ? $productAudit->price : $showPrice;
        if ($modifyPrice != $showPrice && $effectTime != $showEffectTime && !empty($effectTime)) {
            if (!preg_match('/^[1-9]\d{3}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])\s+(20|21|22|23|[0-1]\d)(:[0-5]\d:[0-5]\d)?$/', $effectTime)) {
                return $this->jsonFailed(__('生效时间格式不正确！', [], 'controller/product'));
            }

            $effectTime = date('Y-m-d H:00:00', strtotime(analyze_time_string($effectTime)));
            if (Carbon::now()->addDay(1)->format('Y-m-d H:i:s') > $effectTime) {
                return $this->jsonFailed(__('生效时间必须大于当前时间+24小时！', [], 'controller/product'));
            }
        }

        end:

        try {
            $result = app(ProductAuditService::class)->modifyProductPrice($product->product_id, $this->sellerId, customer()->getCountryId(), floatval($modifyPrice), $effectTime, $priceDisplay);
            $auditPrice = $result == 2;
        } catch (\Throwable $e) {
            Logger::modifyPrices('修改价格报错：' . $e->getMessage(), 'error');
            return $this->jsonFailed();
        }

        return $this->jsonSuccess(['is_need_audit' => $auditPrice]);
    }

    /**
     * 审核记录 取消
     * @return JsonResponse
     */
    public function auditCancel()
    {
        $id = $this->request->post('id', 0);
        if ($id < 1) {
            return $this->jsonFailed();
        }

        try {
            app(ProductAuditService::class)->cancelProductAudit($id, $this->sellerId);
        } catch (Exception $e) {
            if ($e->getCode() == 403) {
                return $this->jsonFailed(__('该审核记录已经被审核完成，无法取消！', [], 'controller/product'));
            }
            return $this->jsonFailed();
        }

        return $this->jsonSuccess([], __('取消成功！', [], 'controller/product'));
    }


    /**
     * 查看审核详情
     * @return JsonResponse
     */
    public function getProductAuditInfo()
    {

        $auditId = $this->request->get('audit_id', 0);
        $productId = $this->request->get('product_id', 0);
        $productInfo = app(ProductAuditRepository::class)->getSellerProductAuditInfo($auditId, $productId);
        if ($productInfo === false) {
            return $this->jsonFailed(__('产品不存在！', [], 'controller/product'));
        }
        $productInfo['name'] = SummernoteHtmlEncodeHelper::decode($productInfo['name'], true);
        $productInfo['description'] = SummernoteHtmlEncodeHelper::decode($productInfo['description']);

        return $this->jsonSuccess($productInfo);
    }


    /**
     * Seller的Product List页面，Valid产品的历史价格
     * @return JsonResponse
     */
    public function getPriceHistoryForSeller()
    {
        $productId = request()->post('product_id', 0);

        //产品币制
        $currency = $this->session->get('currency');
        $symbolLeft = $this->currency->getSymbolLeft($currency);
        $symbolRight = $this->currency->getSymbolRight($currency);
        $decimal = $this->currency->getDecimalPlace($currency);//货币小数位数

        if ($productId < 1) {
            return $this->jsonFailed();
        }

        $history = app(ProductRepository::class)->getPriceHistoryForSeller($productId);
        $productInfo = Product::where('product_id', $productId)->select(['sku', 'price'])->first();
        if (!$productInfo) {
            return $this->jsonFailed();
        }

        $results = [];
        foreach ($history as $key => $value) {
            $tmp = [];
            // 价格保留位数
            if ($decimal == 0) {
                $value->price = round($value->price, 0);
            }
            $add_date_format = currentZoneDate($this->session, $value->add_date, 'Y-m-d');//时区转换
            $tmp['price'] = number_format($value->price, $decimal, '.', '');
            $tmp['add_date'] = strtotime($value->add_date);
            $tmp['add_date_format'] = $add_date_format;
            $results[$add_date_format] = $tmp;
        }

        // 价格保留位数
        if ($decimal == 0) {
            $productInfo->price = round($productInfo->price, 0);
        }
        $timestamp = time();
        $add_date_format = currentZoneDate($this->session, date('Y-m-d H:i:s', $timestamp), 'Y-m-d');
        $results[$add_date_format] = [
            'price' => number_format($productInfo->price, $decimal, '.', ''),
            'add_date' => $timestamp,
            'add_date_format' => $add_date_format,
        ];

        $resultsShow = [
            'list' => array_values($results),
            'item_code' => $productInfo->sku,
            'symbol' => $symbolLeft ?: $symbolRight,
            'symbolLeft' => $symbolLeft,
            'symbolRight' => $symbolRight,
        ];
        return $this->jsonSuccess($resultsShow);
    }
}
