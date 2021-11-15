<?php

use App\Helper\CountryHelper;
use App\Logging\Logger;
use App\Catalog\Search\Margin\MarginAgreementSearch;
use App\Models\Buyer\Buyer;
use App\Models\Order\OrderProduct;
use Carbon\Carbon;
use App\Enums\Margin\MarginAgreementStatus;
use App\Repositories\Buyer\BuyerRepository;
use App\Repositories\Margin\MarginRepository;
use App\Repositories\Product\ProductOptionRepository;
use App\Repositories\Product\ProductRepository;
use App\Models\Order\Order;
use App\Enums\Order\OcOrderStatus;
use App\Enums\Margin\MarginPerformerApplyStatus;
use App\Catalog\Search\Margin\MarginAgreementSettlementSearch;
use App\Repositories\Rma\RamRepository;
use App\Enums\Buyer\BuyerType;
use App\Services\Margin\MarginService;
use App\Enums\Margin\MarginAgreementLogType;
use App\Models\Margin\MarginAgreement;
use App\Components\Storage\StorageCloud;
use App\Models\Order\OrderHistory;

/**
 * Class ControllerAccountProductQuotesMargin
 * @property ModelAccountCustomerpartner $model_account_customerpartner
 * @property ModelAccountProductQuotesMargin $model_account_product_quotes_margin
 * @property ModelAccountProductQuotesMarginContract $model_account_product_quotes_margin_contract
 * @property ModelAccountOrder $model_account_order
 * @property ModelCustomerPartnerDelicacyManagement $model_customerpartner_DelicacyManagement
 * @property ModelMessageMessage $model_message_message
 * @property ModelToolImage $model_tool_image
 * @property ModelCatalogProduct $model_catalog_product
 */
class ControllerAccountProductQuotesMargin extends Controller
{

    protected $currencyCode;
    public $precision = null;

    /**
     * ControllerAccountProductQuotesMargin constructor.
     * @param Registry $registry
     */
    public function __construct(Registry $registry)
    {
        parent::__construct($registry);
        if (!$this->customer->isLogged()) {
            if (is_ajax()) {
                $this->response->returnJson(['redirect' => $this->url->link('account/login')]);
            }else{
                session()->set('redirect', $this->url->link('account/wishlist'));
                $this->response->redirect($this->url->link('account/login'));
            }
        }
        $this->currencyCode = $this->session->get('currency');
        // 货币小数位数
        $this->precision = $this->currency->getDecimalPlace($this->currencyCode);
    }

    public function index()
    {

    }

    /**
     * 现货四期，buyer端协议新列表
     * @throws Exception
     */
    public function agreements()
    {
        load()->model('account/product_quotes/margin');
        load()->model('account/product_quotes/margin_contract');
        load()->language('account/product_quotes/wk_product_quotes');
        load()->language('account/product_quotes/margin');
        // 获取数据
        $search = new MarginAgreementSearch(customer()->getId());
        $dataProvider = $search->search($this->request->query->all());

        $list = $dataProvider->getList();

        $data['total'] = $dataProvider->getTotalCount(); // 总计
        $data['list'] = $list;  // 列表
        $data['paginator'] = $dataProvider->getPaginator();
        $data['sort'] = $dataProvider->getSort();
        $data['search'] = $search->getSearchData();
        $data['statistice_info'] = $search->getStatisticsNumber($this->request->query->all());
        $data['statistice_total'] = $search->getStatisticsNumber($this->request->query->all(),true);
        $data['margin_agreement_url'] = url('account/product_quotes/margin/agreements/?version=v4');
        $data['margin_agreemen_download_url'] = url('account/product_quotes/margin/tabMarginDownload/?version=v4');

        $marginList = obj2array($list);
        //获取尾款product 所在的店铺
        $rest_store_seller_id = [];
        $rest_product_list = array_diff(array_unique(array_column($marginList, 'rest_product_id')), array(''));
        if ($rest_product_list) {
            $rest_store_seller_id = $this->model_account_product_quotes_margin_contract->get_store_seller_id($rest_product_list);
            $rest_store_seller_id = array_combine(array_column($rest_store_seller_id, 'product_id'), array_column($rest_store_seller_id, 'customer_id'));
        }
        //保证金店铺
        $bx_store = $this->config->get('config_customer_group_ignore_check');
        //获取尾款产品的数量
        $get_sell_num_filter = $new_agreement_list = [];
        foreach ($marginList as $k => $v) {
            if ($v['rest_product_id']) {
                $rest_product_store_seller_id = isset($rest_store_seller_id[$v['rest_product_id']]) ? $rest_store_seller_id[$v['rest_product_id']] : 0;
                if (!in_array($rest_product_store_seller_id, $bx_store)) {     //有尾款产品，并且尾款产品不在保证金店铺   ---新的保证金协议
                    $get_sell_num_filter[] = array(
                        'product_id' => $v['rest_product_id'],
                        'agreement_id' => $v['id']
                    );
                    $new_agreement_list[] = $v['id'];
                }
            }
        }
        //获取新协议的售卖数量
        $restSellNum = [];
        if ($get_sell_num_filter) {
            $restSellNum = $this->model_account_product_quotes_margin_contract->get_rest_product_sell_num($get_sell_num_filter);
        }
        $isFuturesToMarginList = $this->model_account_product_quotes_margin->isFuturesToMarginMoreVersion(array_column($marginList, 'id'));//是否是期货转现货
        //状态名称
        $marginStatusList = app(MarginRepository::class)->getAgreementStatus(true);
        $data['marginStatusList'] = $marginStatusList;
        $newMarginStatusList = array_column($marginStatusList, NULL, 'id'); //减少关联表查询
        foreach ($marginList as $key => &$value) {
            $value['status_name'] = isset($newMarginStatusList[$value['status']]) ? $newMarginStatusList[$value['status']]['name'] : '';
            $value['status_color'] = isset($newMarginStatusList[$value['status']]) ? $newMarginStatusList[$value['status']]['color'] : '';
            $value['image_url'] = StorageCloud::image()->getUrl($value['product_image'], ['w' => 60, 'h' => 60]);
            $productInfo = app(ProductRepository::class)->getProductInfoByProductId($value['product_id']);
            $value['product_tags'] = $productInfo['tags'] ?? [];

            $newTimezone = changeOutPutByZone($value['update_time'], $this->session);
            $value['update_day'] = substr($newTimezone, 0, 10);
            $value['update_hour'] = substr($newTimezone, 11);
            $value['days_left'] = $this->model_account_product_quotes_margin->daysLeftWarning($value);
            $value['count_down'] = $this->model_account_product_quotes_margin->countDownByStatus($value);
            //已经售卖的量=已经售卖的-RMA的量
            if (in_array($value['id'], $new_agreement_list)) {   //新协议
                $agreementNew = 1;
                $qtyNum = (isset($restSellNum[$value['id']][$value['rest_product_id']])) ? $restSellNum[$value['id']][$value['rest_product_id']] : 0;
            } else {
                $agreementNew = 0;
                $restOrderArr = explode(',', $value['rest_order_ids']);
                $qtyRamAll = 0;
                foreach ($restOrderArr as $restOrderId) {
                    if ($restOrderId && $value['rest_product_id']) {
                        $qty = $this->model_account_product_quotes_margin->getRmaQtyPurchaseAndSales($restOrderId, $value['rest_product_id']);
                        $qtyRamAll += $qty;
                    }
                }
                $value['sum_rma_qty'] = $qtyRamAll;
                $qtyNum = (intval($value['sum_purchase_qty'])) - (intval($value['sum_rma_qty']));
            }
            $value['num_complete'] = $qtyNum;
            $value['last_num'] = max($value['num'] - $qtyNum, 0);
            $value['agreement_new'] = $agreementNew;
            $value['is_can_cancel'] = $this->model_account_product_quotes_margin->isCanCancel($value)['ret'];
            //是否允许添加共同履约人
            $value['is_can_performer_add'] = $this->model_account_product_quotes_margin->isCanPerformerAdd($value)['ret'];
            //是否允许添加至购物车
            $value['is_can_cart'] = $this->model_account_product_quotes_margin->isCanCart($value)['ret'];
            $resultMargin = isset($isFuturesToMarginList[$value['id']]) ? $isFuturesToMarginList[$value['id']] : [];
            $value['futures_id'] = ($resultMargin && isset($resultMargin['futures_id'])) ? ($resultMargin['futures_id']) : (0);//是否是期货转现货
            $value['futures_contract_id'] = ($resultMargin && isset($resultMargin['contract_id'])) ? ($resultMargin['contract_id']) : (0);//0 期货一期 ，非0 期货二期
        }

        $data['margin_list'] = $marginList;
        $countMwp = $data['statistice_total']['total_number'];
        $countMwp = $countMwp > 99 ? '99+' : ($countMwp < 1 ? '' : strval($countMwp));
        $data['margin_tab_mark_count'] = $countMwp;
        $data['country'] = session('country');

        return $this->render('account/product_quotes/margin/agreements', $data);
    }

    /**
     * 现货四期,新协议母模板
     * @throws Exception
     */
    public function agreementDetail()
    {
        $id = $this->request->get('id', 0);
        $customerId = customer()->getId();
        $quotesLanaguage = load()->language('account/product_quotes/wk_product_quotes');
        $this->load->model('account/product_quotes/margin');
        $data['id'] = $id;
        $marginInfo = app(MarginRepository::class)->getMarginAgreementInfo($id);
        if (empty($marginInfo) || $marginInfo['agreement_program_code'] !== MarginAgreement::PROGRAM_CODE_V4) {
            $this->response->setStatusCode(404);
            $data['continue'] = url('account/product_quotes/wk_quote_my');
            $data['text_error'] = $data['heading_title'] = 'The page you requested cannot be found!';
            return $this->render('error/not_found', $data,'home');
        }
        //防止修改url中id
        if ($marginInfo['agreement_buyer_id'] != $customerId) {
            /** @var ModelAccountProductQuotesMarginContract $marginContractModel */
            $marginContractModel = load()->model('account/product_quotes/margin_contract');
            $performerList = $marginContractModel->get_common_performer($id);
            $buyerIds = array_column($performerList, 'buyer_id');
            if (!in_array($customerId, $buyerIds)) {
                $this->response->setStatusCode(404);
                $data['continue'] = url('account/product_quotes/wk_quote_my');
                $data['text_error'] = $data['heading_title'] = 'The page you requested cannot be found!';
                return $this->render('error/not_found', $data, 'home');
            }
        }
        $appliedPerformer = 0; //是否申请了共同履约人
        $order_id = 0;
        $marginProcess = $this->model_account_product_quotes_margin->getMarginProcessByMarginId($id); //保证金与订单关系
        if ($marginProcess['process_status'] >= 2) {
            $performerAppliedInfo = app(MarginRepository::class)->getPerformerApply($id,true);
            $appliedPerformer = !empty($performerAppliedInfo) ? 1 : 0;
            $order_id = isset($marginProcess['advance_order_id']) ? $marginProcess['advance_order_id'] : 0;
        }
        $data['order_id'] = $order_id;
        $data['margin_info'] = $marginInfo;
        $data['applied_performer'] = $appliedPerformer;
        $data['margin_process'] = $marginProcess;
        $data['tab_title_margin_bids'] = $quotesLanaguage['tab_title_margin_bids'];
        $data['currency'] = session()->get('currency');

        $this->document->setTitle($quotesLanaguage['tab_title_margin_bids']);

        return $this->render('account/product_quotes/margin/agreement_detail', $data, 'buyer');
    }

    /**
     * 现货四期，buyer端 协议tab1 协议详情
     * @throws Exception
     */
    public function agreementInfo()
    {
        $language = load()->language('account/product_quotes/margin');
        $marginId = (int)$this->request->get('id', 0);
        $marginInfoDetail = app(MarginRepository::class)->getMarginAgreementInfo($marginId);
        if ($marginInfoDetail) {
            $productInfo = app(ProductRepository::class)->getProductInfoIncludeAttributeAndTags($marginInfoDetail['seller_id'], $marginInfoDetail['product_id']);
            $marginInfoDetail['color_and_material'] = join(' + ', $productInfo->attributes);
            $marginInfoDetail['show_image'] = StorageCloud::image()->getUrl($marginInfoDetail['product_image'], ['w' => 60, 'h' => 60]);
            $marginInfoDetail['tags'] = $productInfo->tags;
            $marginInfoDetail['buyer_seller_message'] = app(MarginRepository::class)
                ->getMessagesWithBuyerAndSeller($marginInfoDetail['id'], $marginInfoDetail['is_bid'], $marginInfoDetail['buyer_id'], $marginInfoDetail['seller_id']);
            $marginInfoDetail['discountShow'] = is_null($marginInfoDetail['discount']) ? '' : round(100 - $marginInfoDetail['discount']);
            $data['detail'] = $marginInfoDetail;
            $data['tips_future_to_margin_agreement'] = $language['tips_future_to_margin_agreement'];
            $data['future_to_margin_with_fid'] = (int)app(MarginRepository::class)->checkMarginIsFuture2MarginWithReturn($marginInfoDetail['id']);  //期货转现货
            $data['currency'] = session()->get('currency');
            $data['country'] = session('country');

            return $this->render('account/product_quotes/margin/agreement_info', $data);
        }
        return $this->render('account/product_quotes/margin/agreement_info', []);
    }

    /**
     * 现货四期，buyer端 协议tab2 保证金 ; 支付完成才会展示此tab，
     * @throws Exception
     */
    public function agreementDeposit()
    {
        $id = $this->request->get('id', 0);
        $orderId = $this->request->get('order_id', 0);
        $orderInfo = Order::query()->find($orderId);
        $marginInfo = app(MarginRepository::class)->getMarginAgreementInfo($id);
        if ($marginInfo && $orderInfo) {
            $advanceProductInfo = app(ProductRepository::class)->getProductInfoByProductId($marginInfo['advance_product_id']);
            //此页面是支付完成才会展示，所以order_history中应该有数据
            $orderHistory = OrderHistory::query()->where('order_id',$orderId)->where('order_status_id',OcOrderStatus::COMPLETED)->first();
            $orderDetail = [
                'order_status' => OcOrderStatus::getDescription($orderInfo->order_status_id),
                'order_id' => $orderInfo->order_id,
                'date_paid' => !empty($orderHistory) ? $orderHistory->date_added : $orderInfo->date_modified,
            ];
            // 头款可以通过advance_product_id找到  orderInfo中存在头款订单存在多个产品的情况
            $orderProductInfo = OrderProduct::query()->where('product_id',$marginInfo['advance_product_id'])->first();
            $marginDetail = [
                'show_image' => StorageCloud::image()->getUrl($marginInfo['product_image'], ['w' => 60, 'h' => 60]),
                'sku' => $advanceProductInfo['sku'] ?? '',
                'deposit_amount' => $orderProductInfo->price + $orderProductInfo->service_fee,
                'deposit_product_quantity' => $orderProductInfo->quantity, // 这个应该恒定为1
                'total_deposited_products' => $orderProductInfo->price + $orderProductInfo->service_fee,
                'tag' => $advanceProductInfo['tags'] ?? [],
            ];
            $data['rest_order_url'] = (customer()->getId() == $orderInfo->customer_id) ? $this->url->link('account/order/purchaseOrderInfo', 'order_id=' . $orderInfo->order_id, true) : '';
            $data['order_detail'] = $orderDetail;
            $data['margin_detail'] = $marginDetail;
            $data['currency'] = session()->get('currency');

            return $this->render('account/product_quotes/margin/agreement_deposit', $data);
        }
        return $this->render('account/product_quotes/margin/agreement_deposit', []);
    }

    //现货四期，buyer端 协议tab3 共同履约人
    public function agreementPartner()
    {
        $id = $this->request->get('id', 0);
        $marginInfo = app(MarginRepository::class)->getPerformerApply($id, true);
        if ($marginInfo) {
            $marginInfo['seller_approval_status_show'] = MarginPerformerApplyStatus::getDescription($marginInfo['seller_approval_status']);
            $marginInfo['check_result_show'] = $marginInfo['check_result'] == 0 ? 'N/A' : MarginPerformerApplyStatus::getDescription($marginInfo['check_result']);
            $marginInfo['buyer_account_type'] = app(BuyerRepository::class)->getTypeById($marginInfo['performer_buyer_id']); // 1 上门取货 2 一件代发
            $marginInfo['buyer_account_name'] = BuyerType::getDescriptionNew($marginInfo['buyer_account_type']);

            $data['partner_detail'] = $marginInfo;
            return $this->render('account/product_quotes/margin/agreement_partner', $data);
        }
        return $this->render('account/product_quotes/margin/agreement_partner', []);
    }

    /**
     * 现货四期，buyer端 协议tab4 交易记录
     * @throws Exception
     */
    public function agreementSettlement()
    {
        $id = $this->request->get('id', 0);
        $cwfLanaguage = load()->language('common/cwf');
        load()->model('account/product_quotes/margin_contract');
        $marginInfo = app(MarginRepository::class)->getMarginAgreementInfo($id);
        if ($marginInfo) {
            $productId = $marginInfo['product_id'];
            $customerId = customer()->getId();
            $search = new MarginAgreementSettlementSearch($id, $productId);
            $dataProvider = $search->search($this->request->query->all());
            $list = $dataProvider->getList();
            $data['total'] = $dataProvider->getTotalCount();
            $data['list'] = $list;
            $data['paginator'] = $dataProvider->getPaginator();
            $orderLists = $list->toArray();
            foreach ($orderLists as $key => $rest_order) {
                $orderLists[$key]['is_collection_from_domicile'] = app(BuyerRepository::class)->getTypeById($rest_order['buyer_id']);
                $orderLists[$key]['rma_details'] = app(RamRepository::class)->getPurchaseOrderRmaWithSumInfo($rest_order['order_id'], $rest_order['order_product_id']);
                $orderLists[$key]['rest_order_url'] = ($customerId == $rest_order['buyer_id']) ? $this->url->link('account/order/purchaseOrderInfo', 'order_id=' . $rest_order['order_id'], true) : '';
                $orderLists[$key]['origin_sub_total'] = ($rest_order['price'] + $rest_order['service_fee_per'] + $rest_order['freight_per'] + $rest_order['package_fee']) * $rest_order['quantity'];
                $orderLists[$key]['sub_total'] = $orderLists[$key]['origin_sub_total'] - $rest_order['coupon_amount'] - $rest_order['campaign_amount'];
                $orderLists[$key]['service_fee'] = $rest_order['service_fee_per'];
                $orderLists[$key]['freight_fee'] = ($rest_order['freight_per'] + $rest_order['package_fee']) * $rest_order['quantity'];
                $orderLists[$key]['base_ship_fee'] = $orderLists[$key]['is_collection_from_domicile'] == 1 ? 0.00 : $rest_order['base_freight'] - $rest_order['freight_difference_per'];
                $orderLists[$key]['freight_per'] = $orderLists[$key]['is_collection_from_domicile'] == 1 ? $rest_order['package_fee'] : $rest_order['freight_per'] + $rest_order['package_fee'];
            }
            $restSellNum = $this->model_account_product_quotes_margin_contract->get_rest_product_sell_num([
                [
                    'product_id' => $productId, //现在这个$productId = $resetProductId
                    'agreement_id' => $marginInfo['id']
                ]
            ]);
            $soldedNum = $restSellNum[$marginInfo['id']][$productId] ?? 0; //已售卖

            $data['restOrderList'] = $orderLists;
            $data['marginInfo'] = $marginInfo;
            $data['agreement_statistics'] = [
                'total_number' => $marginInfo['num'], //协议总数
                'total_sold' => (int)$soldedNum,
                'total_unsold' => max($marginInfo['num'] - (int)$soldedNum, 0)
            ];
            //尾款支付情况
            $data['due_payment'] = app(MarginRepository::class)->getAgreementDuePayInfo($marginInfo['id']);
            $data['customer_id'] = $customerId;
            $data['rma_url'] = url('account/rma_order_detail&rma_id=');
            $data['base_freight_label'] = $cwfLanaguage['base_freight_label'];
            $data['package_fee_label'] = $cwfLanaguage['package_fee_label'];
            $data['isEurope'] = customer()->isEurope();
            $data['currency'] = session()->get('currency');

            return $this->render('account/product_quotes/margin/agreement_settlement', $data);
        }
        return $this->render('account/product_quotes/margin/agreement_settlement', []);
    }

    /**
     * 现货四期 下载
     * @throws Exception
     */
    public function tabMarginDownload()
    {
        load()->model('account/product_quotes/margin');
        load()->model('account/product_quotes/margin_contract');

        load()->language('account/customerpartner/margin');
        load()->language('account/product_quotes/margin');
        load()->language('account/product_quotes/wk_product_quotes');
        load()->language('account/product_quotes/margin');

        // 获取数据
        $search = new MarginAgreementSearch(customer()->getId());
        $dataProvider = $search->search($this->request->query->all(), true);

        $margin_list = $dataProvider->getList();
        $margin_list = obj2array($margin_list);

        //获取尾款product 所在的店铺
        $rest_store_seller_id = [];
        $rest_product_list = array_diff(array_unique(array_column($margin_list, 'rest_product_id')), array(''));
        if ($rest_product_list) {
            $rest_store_seller_id = $this->model_account_product_quotes_margin_contract->get_store_seller_id($rest_product_list);
            $rest_store_seller_id = array_combine(array_column($rest_store_seller_id, 'product_id'), array_column($rest_store_seller_id, 'customer_id'));
        }
        //保证金店铺
        $bx_store = $this->config->get('config_customer_group_ignore_check');
        //获取尾款产品的数量
        $get_sell_num_filter = $new_agreemnt_list = [];
        //状态名称
        $marginStatusList = $this->model_account_product_quotes_margin->marginStatusList();
        $newMarginStatuList = array_column($marginStatusList, NULL, 'id'); //减少关联表查询
        foreach ($margin_list as $k => $v) {
            if ($v['rest_product_id']) {
                $rest_product_store_seller_id = isset($rest_store_seller_id[$v['rest_product_id']]) ? $rest_store_seller_id[$v['rest_product_id']] : 0;
                if (!in_array($rest_product_store_seller_id, $bx_store)) {     //有尾款产品，并且尾款产品不在保证金店铺   ---新的保证金协议
                    $get_sell_num_filter[] = [
                        'product_id' => $v['rest_product_id'],
                        'agreement_id' => $v['id']
                    ];
                    $new_agreemnt_list[] = $v['id'];
                }
            }
        }
        //获取新协议的售卖数量
        $rest_sell_num = [];
        if ($get_sell_num_filter) {
            $rest_sell_num = $this->model_account_product_quotes_margin_contract->get_rest_product_sell_num($get_sell_num_filter);
        }
        //12591 B2B记录各国别用户的操作时间
        $country = session('country', 'USA');
        $fromZone = CountryHelper::getTimezoneByCode('USA');
        $toZone = CountryHelper::getTimezoneByCode($country);
        $time = dateFormat($fromZone, $toZone, date("Ymd", time()), 'Ymd');
        //12591 end
        $filename = 'Marginbids_' . $time . '.csv';
        $head = [
            'Agreement ID',
            'Store',
            'Item Code',
            'Days of Agreement',
            'Agreement Unit Price',
            'Agreement Quantity',
            $this->language->get('column_margin_completed_qty'),//现货保证金协议完成数量
            $this->language->get('column_margin_front_money'),//现货保证金协议定金金额=执行价*比率*数量
            'Deposit Order ID',//$this->language->get('column_margin_order_id'),//定金订单号,
            $this->language->get('column_margin_tail_price'),
            $this->language->get('column_margin_agreement_money'),
            'Time of Effect',
            'Time of Failure',
            'Last Modified',
            'Status',
        ];
        $line = [];
        // 6 8 9 10
        $noNaStatus = [
            MarginAgreementStatus::SOLD,
            MarginAgreementStatus::COMPLETED,
            MarginAgreementStatus::BACK_ORDER,
            MarginAgreementStatus::TERMINATED
        ];
        foreach ($margin_list as $key => &$value) {
            //已经售卖的量=已经售卖的-RMA的量
            if (in_array($value['id'], $new_agreemnt_list)) {   //新协议
                $num_complete = (isset($rest_sell_num[$value['id']][$value['rest_product_id']])) ? $rest_sell_num[$value['id']][$value['rest_product_id']] : 0;
            } else {
                $rest_order_arr = explode(',', $value['rest_order_ids']);
                $qty_ram_all = 0;
                foreach ($rest_order_arr as $rest_order_id) {
                    if ($rest_order_id && $value['rest_product_id']) {
                        $qty = $this->model_account_product_quotes_margin->getRmaQtyPurchaseAndSales($rest_order_id, $value['rest_product_id']);
                        $qty_ram_all += $qty;
                    }
                }
                $value['sum_rma_qty'] = $qty_ram_all;
                $num_complete = (intval($value['sum_purchase_qty'])) - (intval($value['sum_rma_qty']));
            }
            if (!in_array($value['status'], $noNaStatus)) {
                $num_complete = 'N/A';
            }
            $advance_order_id = $value['advance_order_id'];//定金订单号
            if (!in_array($value['status'], $noNaStatus)) {
                $advance_order_id = 'N/A';
            }
            $effect_time = $value['effect_time'];//协议开始时间
            $expire_time = $value['expire_time'];//协议结束时间
            if (!in_array($value['status'], $noNaStatus)) {
                $effect_time = 'N/A';
                $expire_time = 'N/A';
            }
            $advance_price = $value['deposit_per'];//现货保证金头款单价
            $rest_price = bcsub($value['price'], $advance_price, 2);//现货保证金尾款单价=执行价-(执行价*比率)
            $money_agreement = bcmul($value['price'], $value['num'], 2);//保证金协议金额
            if ($value['buyer_ignore'] == 1) {
                $value['status_name'] = 'Ignore';
            }
            $value['status_name'] = isset($newMarginStatuList[$value['status']]) ? $newMarginStatuList[$value['status']]['name'] : 'N/A';

            $line[] = [
                "\t" . $value['agreement_id'],//Agreement ID
                html_entity_decode($value['screenname']),//Store
                $value['sku'],//Item Code
                $value['day'],//Days of Agreement
                $this->currency->format($value['price'], $this->session->get('currency'), false, true),//Agreement Unit Price = 执行价
                $value['num'],//Agreement QTY
                $num_complete,//现货保证金协议完成数量
                $this->currency->format($value['money'], $this->session->get('currency'), false, true),//现货保证金协议定金金额=执行价*比率*数量
                $advance_order_id,//定金订单号
                $this->currency->format($rest_price, $this->session->get('currency'), false, true),//现货保证金尾款单价
                $this->currency->format($money_agreement, $this->session->get('currency'), false, true),//现货保证金协议金额 = 执行价*数量
                "\t" . $effect_time,//协议开始时间
                "\t" . $expire_time,//协议结束时间
                "\t" . $value['update_time'],//Last Modified
                $value['status_name'],//Status
            ];
        }
        outputCsv($filename, $head, $line, $this->session);
    }

    //现货四期，区分新协议和老协议，老协议走原来逻辑和页面，新协议走最新页面,tb_sys_margin_agreement里的program_code （v4）来区分
    public function detail_list()
    {
        if (!$this->customer->isLogged()) {
            session()->set('redirect', $this->url->link('account/product_quotes/wk_quote_my', '', true));
            $this->response->redirect($this->url->link('account/login', '', true));
        }

        $id = intval(get_value_or_default($this->request->get, 'id', 0));
        $actionother = strval(get_value_or_default($this->request->get, 'actionother', ''));
        $customer_id = $this->customer->getId();

        $data = array();
        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        if (isset($this->session->data['success'])) {
            $data['success'] = session('success');
            $this->session->remove('success');
        } else {
            $data['success'] = '';
        }
        $this->language->load('account/product_quotes/margin');
        $this->load->model('account/product_quotes/margin');

        $data['id'] = $id;

        $marginInfo = $this->model_account_product_quotes_margin->getInfo($id);

        //在此区分新老协议
        if ($marginInfo['program_code'] === MarginAgreement::PROGRAM_CODE_V4) {
            return response()->redirectTo(url('account/product_quotes/margin/agreementDetail&id=' . $id));
        }

        $margin_buyer_id = intval($marginInfo['buyer_id']);
        if ($margin_buyer_id != $customer_id) {
            $actionother = '';
        }

        //保证金与订单关系
        $marginProcess    = $this->model_account_product_quotes_margin->getMarginProcessByMarginId($id);
        $order_id         = isset($marginProcess['advance_order_id']) ? $marginProcess['advance_order_id'] : 0;
        $margin_agreement_id = isset($marginProcess['ma_aid']) ? $marginProcess['ma_aid'] : '......';
        $data['order_id'] = $order_id;

        $data['breadcrumbs'] = [
            [
                'text'      => $this->language->get('text_home'),
                'href'      => $this->url->link('common/home'),
                'separator' => false
            ],
            [
                'text'      => $this->language->get('text_bid_list'),
                'href'      => $this->url->link('account/product_quotes/wk_quote_my', '&tab=2'),
                'separator' => $this->language->get('text_separator')
            ],
            [
                'text'      => $this->language->get('heading_title_margin_details'),
                'href'      => $this->url->link('account/product_quotes/margin/detail_list', 'id='.$id),
                'separator' => $this->language->get('text_separator')
            ]
        ];

        $this->document->setTitle($this->language->get('heading_title_margin_details'));
        $data['heading_title_margin_details_long'] = sprintf($this->language->get('heading_title_margin_details_long'), $margin_agreement_id);

        $data['header']         = $this->load->controller('common/header');
        $data['footer']         = $this->load->controller('common/footer');
        $data['column_left']    = $this->load->controller('common/column_left');
        $data['column_right']   = $this->load->controller('common/column_right');
        $data['content_top']    = $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');
        $data['href_go_back']   = $this->url->link('account/product_quotes/wk_quote_my', '&tab=2', true);
        $data['actionother']     = $actionother;
        $this->response->setOutput($this->load->view('account/product_quotes/margin/detail_list', $data));
    }

    public function protocol_detail()
    {
        if (!$this->customer->isLogged()) {
            if (!$this->customer->isLogged()) {
                session()->set('redirect', $this->url->link('account/product_quotes/margin/detail_list', '', true));
                $this->response->redirect($this->url->link('account/login', '', true));
            }
        }
        $data            = [];
        $decimal_place=$this->currency->getDecimalPlace($this->session->data['currency']);
        $data['decimal_place']=$decimal_place;
        $data['no_data'] = false;
        $id = intval(get_value_or_default($this->request->get, 'id', 0));
        $actionother = strval(get_value_or_default($this->request->get, 'actionother', ''));
        if ($id < 1) {
            $data['no_data'] = true;
        }


        $this->language->load('account/customerpartner/margin');
        $this->language->load('account/product_quotes/margin');
        $this->load->model('account/product_quotes/margin');


        $marginInfoDetail = $this->model_account_product_quotes_margin->getInfoDetail($id);
        if (!$marginInfoDetail) {
            $data['no_data'] = true;
        } else {
            $margin_buyer_id  = $marginInfoDetail['buyer_id'];
            $advance_product_id = $marginInfoDetail['advance_product_id'];
            $seller_id = intval($marginInfoDetail['seller_id']);



            $customer_id = $this->customer->getId();
            $country_id  = $this->customer->getCountryId();
            if (107 == $country_id) {//Japan
                $marginInfoDetail['price'] = sprintf('%d', round($marginInfoDetail['price']));
                $marginInfoDetail['money'] = sprintf('%d', round($marginInfoDetail['money']));

            } else {
                $marginInfoDetail['price'] = sprintf('%.2f', round($marginInfoDetail['price'], 2));
                $marginInfoDetail['money'] = sprintf('%.2f', round($marginInfoDetail['money'], 2));
            }
            $marginInfoDetail['price_show'] = $this->currency->format($marginInfoDetail['price'], $this->session->data['currency'], false, true);
            $marginInfoDetail['money_show'] = $this->currency->format($marginInfoDetail['money'], $this->session->data['currency'], false, true);


            $data['detail']       = $marginInfoDetail;
            $data['firstMessage'] = [];//一维数组
            $data['otherMessage'] = [];//二维数组
            $i                    = 0;
            foreach ($marginInfoDetail['message'] as $key => $value) {
                if($value['customer_id'] == 0){
                    $value['screenname'] = 'Marketplace';
                } elseif ($value['customer_id'] == -1) {
                    $value['screenname'] = 'System';
                }
                if ($i == 0 && $value['customer_id'] == $margin_buyer_id) {
                    $data['firstMessage'] = $value;
                } else {
                    if ($value['customer_id'] == $customer_id) {
                        $value['fullname_show'] = 'Me';
                    } else {
                        $value['fullname_show'] = $value['fullname'];
                    }
                    $data['otherMessage'][] = $value;
                }
                $i++;
            }


            //保证金有效期说明：如果Status = 1 Applied、2 Pending、5 Time Out、3 Approved、4 Rejected、7 Canceled则不显示Margin Validity
            $data['isShowValidity'] = in_array($marginInfoDetail['status'], [1, 2, 5, 3, 4, 7]) ? 0 : 1;


            if ($margin_buyer_id == $customer_id) {
                //Edit Margin Agreement：Status = 1 Applied、4 Rejected、7 Canceled、5 Time Out 出现此模块的按钮，其他状态不出现此模块与按钮
                $is_show_edit = in_array($marginInfoDetail['status'], [1, 4, 7, 5]) ? 1 : 0;
                if ($actionother == 'reapplied') {
                    $is_show_edit = $this->model_account_product_quotes_margin->isCanReapplied($marginInfoDetail)['ret'];
                }
                if($advance_product_id > 0){
                    //有头款产品，则不允许重新申请啦
                    $is_show_edit = 0;
                    $actionother  = '';
                }
            } else {
                $is_show_edit = 0;
                $actionother  = '';
            }
            $data['is_show_edit'] = $is_show_edit;

            $data['protocol_edit_link'] = $this->url->link('account/product_quotes/margin/protocol_edit', '', true);
            $data['country_id']         = $this->customer->getCountryId();
            $data['sold_qty']           = $marginInfoDetail['quantity'];//库存
            $data['actionother']        = $actionother;
            $data['send_message_link']  = $this->url->link('message/seller/addMessage', 'receiver_id=' . $seller_id, true);


            //共同履约人的显示规则：
            //主履约人 看到 从履约人的信息
            //从履约人 看到 主履约人的信息
            if ($margin_buyer_id == $customer_id) {
                //从履约人的信息
                $sql = "SELECT
        'Secondary' AS 'partner'
        ,c.email
        ,c.user_number
        ,mpa.check_result,mpa.seller_approval_status
    FROM tb_sys_margin_performer_apply AS mpa
    LEFT JOIN oc_customer AS c ON c.customer_id=mpa.performer_buyer_id
    WHERE mpa.agreement_id={$id}
    ORDER BY mpa.create_time DESC
    LIMIT 1";

                $performer_list = $this->db->query($sql)->rows;

            } else {
                //主履约人的信息
                $sql = "SELECT
        'Primary' AS 'partner'
        ,c.email
        ,c.user_number
        ,'-1' AS check_result
    FROM oc_customer AS c
    WHERE c.customer_id={$margin_buyer_id}
    LIMIT 1";

                $performer_list = $this->db->query($sql)->rows;
            }


            $data['performer_list'] = $performer_list;
        }


        $this->response->setOutput($this->load->view('account/product_quotes/margin/protocol_detail', $data));
    }

    public function protocol_edit()
    {
        $this->language->load('account/product_quotes/margin');
        $this->load->model('account/product_quotes/margin');


        $id      = intval(get_value_or_default($this->request->post, 'id', 0));
        $num     = intval(get_value_or_default($this->request->post, 'num', 0));
        $price   = get_value_or_default($this->request->post, 'price', 0);
        $day     = intval(get_value_or_default($this->request->post, 'day', 0));
        $message = trim(get_value_or_default($this->request->post, 'message', ''));
        $last_update_time = trim(get_value_or_default($this->request->post, 'last_update_time', ''));
        $actionother = trim(get_value_or_default($this->request->post, 'actionother', ''));


        if (!$this->customer->isLogged()) {
            $json['ret'] = 0;
            $json['msg'] = 'Please refresh the page.';
            goto end;
        }
        if ((!request()->isMethod('POST'))) {
            $json['ret'] = 0;
            $json['msg'] = 'Please refresh the page.';
            goto end;
        }
        if ($id < 1) {
            $json['ret'] = 0;
            $json['msg'] = 'Please refresh the page.';
            goto end;
        }



        $marginInfo = $this->model_account_product_quotes_margin->getInfo($id);

        //Edit Margin Agreement：Status = 1 Applied、4 Rejected、7 Canceled、5 Time Out 出现此模块的按钮，其他状态不出现此模块与按钮
        if ( !in_array($marginInfo['status'], [1, 4, 7, 5])) {
            $json['ret'] = 0;
            $json['msg'] = 'Status of margin agreement has been changed. This agreement cannot be edited.';
            goto end;
        }


        if ($actionother == 'reapplied') {
            $ret = $this->model_account_product_quotes_margin->isCanReapplied($marginInfo);
            if ($ret['ret'] == 0) {
                $json['ret'] = 0;
                $json['msg'] = $ret['msg'];
                goto end;
            }
        }


        if($marginInfo['update_time'] != $last_update_time){
            $json['ret'] = 0;
            $json['msg'] = $this->language->get('error_date_updated');
            goto end;
        }

        if($this->customer->getCountryId() == JAPAN_COUNTRY_ID){
            $precision = 0;
        }else{
            $precision = 2;
        }
        $price = round($price, $precision);
        $payment_ratio    = $marginInfo['payment_ratio'];
        $deposit_per = round($price * $payment_ratio / 100, $precision);
        $money            = round($num * $deposit_per, $precision);

        $data            = [];
        $data['id']      = $id;
        $data['price']   = $price;
        $data['day']     = $day;
        $data['num']     = $num;
        $data['money']   = $money;
        $data['deposit_per']   = $deposit_per;
        $data['status']   = 1;
        $data['message'] = $message;
        $data['actionother'] = $actionother;
        $this->model_account_product_quotes_margin->editMarginAgreement($data);


        $json['ret'] = 1;
        $json['msg'] = 'Success';
        $json['redirect_url'] = str_replace('&amp;', '&', $this->url->link('account/product_quotes/margin/detail_list', 'id='.$id, true));

        end:
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));

    }


    /**
     * 取消协议申请
     * @throws Exception
     */
    public function protocol_cancel(){
        $this->language->load('account/product_quotes/margin');
        $this->load->model('account/product_quotes/margin');
        $this->load->model('account/product_quotes/margin_contract');

        $margin_id      = intval(get_value_or_default($this->request->post, 'margin_id', null));//主键
        $last_update_time = trim(get_value_or_default($this->request->post, 'last_update_time', ''));

        if (!$this->customer->isLogged()) {
            $json['error'] = 'Please refresh the page.';
            goto end;
        }
        if ((!request()->isMethod('POST'))) {
            $json['error'] = 'Please refresh the page.';
            goto end;
        }
        if (!isset($margin_id)) {
            $json['error'] = 'Please refresh the page.';
            goto end;
        }

        $marginInfo = $this->model_account_product_quotes_margin->getInfo($margin_id);
        $product_id = $marginInfo['product_id'];

        //检查是否允许取消
        $resultCanCancel = $this->model_account_product_quotes_margin->isCanCancel($marginInfo);
        if ($resultCanCancel['ret'] == 0) {
            $json['error'] = $resultCanCancel['msg'];
            goto end;
        }
        if($marginInfo['update_time'] != $last_update_time){
            $json['error'] = $this->language->get('error_date_updated');
            goto end;
        }

        $ret = $this->model_account_product_quotes_margin_contract->cancelMarginAgreement($margin_id, null, null, $this->customer->getId(), $marginInfo);

        if($ret){
            //现货四期，记录协议状态变更
            app(MarginService::class)->insertMarginAgreementLog([
                'from_status' => MarginAgreementStatus::APPLIED,
                'to_status' => MarginAgreementStatus::CANCELED,
                'agreement_id' => $margin_id,
                'log_type' => MarginAgreementLogType::APPLIED_TO_CANCELED,
                'operator' => customer()->getNickName(),
                'customer_id' => customer()->getId(),
            ]);
            $json['success'] = $this->language->get('text_cancel_success');
            // 发送站内信给seller
            $agreement_detail = $this->model_account_product_quotes_margin_contract->getMarginAgreementDetail(null, null, $margin_id);
            if(!empty($agreement_detail)){
                $apply_msg_subject = sprintf($this->language->get('margin_cancel_subject'),
                    $agreement_detail['agreement_id']);
                $apply_msg_content = sprintf($this->language->get('margin_cancel_content'),
                    $this->url->link('account/product_quotes/margin_contract/view','agreement_id=' . $agreement_detail['agreement_id'], true),
                    $agreement_detail['agreement_id'],
                    $agreement_detail['nickname'] . ' (' . $agreement_detail['user_number'] . ') ',
                    $agreement_detail['sku'] . '/' . $agreement_detail['mpn']
                );
                $this->load->model('message/message');
                $this->model_message_message->addSystemMessageToBuyer('bid_margin', $apply_msg_subject, $apply_msg_content, $agreement_detail['seller_id']);
            }
        }else{
            $json['error'] = $this->language->get('text_cancel_error');
        }

        end:
        $this->response->returnJson($json);
    }

    //清空现有购物车
    public function preCheckout()
    {
        $customer_id = $this->customer->getId();
        if (!$this->customer->isLogged() || !isset($customer_id)) {
            session()->set('redirect', $this->url->link('account/product_quotes/wk_quote_my', '', true));

            $this->response->redirect($this->url->link('account/login', '', true));
        }

        $product_id          = intval(get_value_or_default($this->request->post, 'product_id', 0));
        $margin_agreement_id = intval(get_value_or_default($this->request->post, 'margin_agreement_id', 0));
        if ($product_id < 1) {
            $json['ret'] = 0;
            $json['msg'] = 'Data Error';
            goto end;
        }


        //验证库存数量是否充足
        $this->load->model('account/product_quotes/margin');
        $result = $this->model_account_product_quotes_margin->checkStockNum($margin_agreement_id);
        if ($result < 1) {
            $json['ret'] = 0;
            $json['msg'] = 'not available in the desired quantity or not in stock!';
            goto end;
        }



        //清空当前购物车
        //$this->cart->clearWithBuyerId($customer_id);


        session()->set('orderMarginType', 'Advance');
        session()->set('margin_agreement_id', $margin_agreement_id);


        $json['ret'] = 1;
        $json['msg'] = 'success';
        end:
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }


    /**
     * 保证金缴纳证明
     * Margin Payment Receipt
     * 参考自：\storage\modification\catalog\controller\account\order.php purchaseOrderInfo()
     */
    public function pay_proof()
    {
        $data              = [];
        $data['has_order'] = 0;
        $id                = $margin_agreement_id = intval(get_value_or_default($this->request->get, 'id', 0));
        if ($id < 1) {
            goto end;
        }


        $customer_id = $this->customer->getId();
        $country_id = $this->customer->getCountryId();
        $this->load->language('account/order');
        $order_id = intval(get_value_or_default($this->request->get, 'order_id', 0));
        $margin_id = intval(get_value_or_default($this->request->get, 'id', null));
        if (!$this->customer->isLogged()) {
            goto end;
        }
        $this->load->model('account/order');
        $this->load->model('account/product_quotes/margin');

        $marginInfo = $this->model_account_product_quotes_margin->getInfo($margin_id);
        $margin_buyer_id = ($marginInfo && isset($marginInfo['buyer_id'])) ? ($marginInfo['buyer_id']) : (null);

        $order_info = $this->model_account_order->getOrder($order_id, $margin_buyer_id);

        //add by xxli
        if ($order_info['order_status_id'] == OcOrderStatus::COMPLETED) {
            $data['can_review'] = true;
        } else {
            $data['can_review'] = false;
        }
        //end xxli
        if ($order_info) {
            $data['has_order'] = 1;
            //获取order_status
            $data['order_status'] = $this->orm->table(DB_PREFIX . 'order_status')->where('order_status_id', $order_info['order_status_id'])->value('name');


            // marketplace
            $this->load->model('account/customerpartner');
            $data['button_order_detail']       = $this->language->get('button_order_detail');
            $data['text_tracking']             = $this->language->get('text_tracking');
            $data['module_marketplace_status'] = $this->config->get('module_marketplace_status');
            // marketplace

            if (isset($this->session->data['error'])) {
                $data['error_warning'] = session('error');

                $this->session->remove('error');
            } else {
                $data['error_warning'] = '';
            }

            if (isset($this->session->data['success'])) {
                $data['success'] = session('success');

                $this->session->remove('success');
            } else {
                $data['success'] = '';
            }

            if ($order_info['invoice_no']) {
                $data['invoice_no'] = $order_info['invoice_prefix'] . $order_info['invoice_no'];
            } else {
                $data['invoice_no'] = '';
            }

            $data['order_id'] = $order_id;
            $data['order_link'] = ($customer_id == $margin_buyer_id) ? $this->url->link('account/order/purchaseOrderInfo', 'order_id=' . $data['order_id']) : '';
            //获取信用卡支付额度
            $balance            = $this->orm->table(DB_PREFIX . 'order_total')->where('order_id', $data['order_id'])->where('code', 'balance')->value('value');
            $data['balance']    = $this->currency->format(0 - $balance, $this->session->data['currency']);
            $data['date_added'] = date($this->language->get('datetime_format'), strtotime($order_info['date_added']));

            if ($order_info['payment_address_format']) {
                $format = $order_info['payment_address_format'];
            } else {
                $format = '{firstname} {lastname}' . "\n" . '{company}' . "\n" . '{address_1}' . "\n" . '{address_2}' . "\n" . '{city} {postcode}' . "\n" . '{zone}' . "\n" . '{country}';
            }

            $find = array(
                '{firstname}',
                '{lastname}',
                '{company}',
                '{address_1}',
                '{address_2}',
                '{city}',
                '{postcode}',
                '{zone}',
                '{zone_code}',
                '{country}'
            );

            $replace = array(
                'firstname' => $order_info['payment_firstname'],
                'lastname'  => $order_info['payment_lastname'],
                'company'   => $order_info['payment_company'],
                'address_1' => $order_info['payment_address_1'],
                'address_2' => $order_info['payment_address_2'],
                'city'      => $order_info['payment_city'],
                'postcode'  => $order_info['payment_postcode'],
                'zone'      => $order_info['payment_zone'],
                'zone_code' => $order_info['payment_zone_code'],
                'country'   => $order_info['payment_country']
            );

            $data['payment_address'] = str_replace(array("\r\n", "\r", "\n"), '<br />', preg_replace(array("/\s\s+/", "/\r\r+/", "/\n\n+/"), '<br />', trim(str_replace($find, $replace, $format))));

            $data['payment_method'] = $order_info['payment_method'];

            if ($order_info['payment_method'] == 'Line Of Credit' && $this->customer->getAdditionalFlag() == 1) {
                $data['payment_method'] = 'Line Of Credit(+1%)';
            }

            if ($order_info['shipping_address_format']) {
                $format = $order_info['shipping_address_format'];
            } else {
                $format = '{firstname} {lastname}' . "\n" . '{company}' . "\n" . '{address_1}' . "\n" . '{address_2}' . "\n" . '{city} {postcode}' . "\n" . '{zone}' . "\n" . '{country}';
            }

            $find = array(
                '{firstname}',
                '{lastname}',
                '{company}',
                '{address_1}',
                '{address_2}',
                '{city}',
                '{postcode}',
                '{zone}',
                '{zone_code}',
                '{country}'
            );

            $replace = array(
                'firstname' => $order_info['shipping_firstname'],
                'lastname'  => $order_info['shipping_lastname'],
                'company'   => $order_info['shipping_company'],
                'address_1' => $order_info['shipping_address_1'],
                'address_2' => $order_info['shipping_address_2'],
                'city'      => $order_info['shipping_city'],
                'postcode'  => $order_info['shipping_postcode'],
                'zone'      => $order_info['shipping_zone'],
                'zone_code' => $order_info['shipping_zone_code'],
                'country'   => $order_info['shipping_country']
            );

            $data['shipping_address'] = str_replace(array("\r\n", "\r", "\n"), '<br />', preg_replace(array("/\s\s+/", "/\r\r+/", "/\n\n+/"), '<br />', trim(str_replace($find, $replace, $format))));

            $data['shipping_method'] = $order_info['shipping_method'];

            $this->load->model('catalog/product');
            $this->load->model('tool/upload');
            $this->load->model('account/product_quotes/margin_contract');
            $this->load->model('tool/image');

            $margin_process = $this->model_account_product_quotes_margin_contract->getMarginProcessDetailByMarginId($margin_id);
            $process_product = array();
            if(isset($margin_process['advance_product_id'])){
                $process_product[] = $margin_process['advance_product_id'];
            }

            // Products
            $data['products'] = array();
            $products         = $this->model_account_order->getOrderProducts($order_id);
            //获取 sub-toal
            //获取 total quote discount
            //获取 total pundage
            // 获取 total price
            $sub_total            = 0;
            $total_quote_discount = 0;
            $total_service_fee    = 0;
            $total_transaction    = 0;
            foreach ($products as $product) {

                if(!in_array($product['product_id'],$process_product)){
                    continue;
                }
                $product_info = $this->model_catalog_product->getProductForOrderHistory($product['product_id']);
                //add by xxli 获取产品的评价信息
                $customerName = $this->model_account_order->getCustomerName($product_info['customer_id']);
                //$reviewResult = $this->model_account_order->getReviewInfo($order_id, $product['order_product_id']);
                //end

                //获取尾款产品
                if ($product_info) {
                    $reorder = str_replace('&amp;', '&', $this->url->link('account/order/reorder', 'order_id=' . $order_id . '&order_product_id=' . $product['order_product_id'], true));
                } else {
                    $reorder = '';
                }
                $tag_array = $this->model_catalog_product->getProductSpecificTag($product['product_id']);
                $tags      = array();
                if (isset($tag_array)) {
                    foreach ($tag_array as $tag) {
                        if (isset($tag['origin_icon']) && !empty($tag['origin_icon'])) {
                            //采用唯一图片  LTL.jpg  part.jpg  combo-img.png
                            $img_url = $this->model_tool_image->getOriginImageProductTags($tag['origin_icon']);
                            $tags[] = '<img data-toggle="tooltip" class="' . $tag['class_style'] . '"  title="' . $tag['description'] . '" style="padding-left: 1px" src="' . $img_url . '">';
                        }
                    }
                }
                //获取这个订单的salesOrder
                $mapProduct = [
                    ['op.product_id', '=', $product['product_id']],
                    ['oco.order_id', '=', $product['order_id']],
                ];

                $new_res  =
                    $this->orm->table(DB_PREFIX . 'order as oco')->
                    leftJoin(DB_PREFIX . 'order_product as op', function ($join) {
                        $join->on('op.order_id', '=', 'oco.order_id');
                    })->
                    leftJoin('tb_sys_order_associated as as', function ($join) {
                        $join->on('as.order_id', '=', 'oco.order_id')->on('as.product_id', '=', 'op.product_id');
                    })->
                    leftJoin('tb_sys_customer_sales_order as o', 'o.id', '=', 'as.sales_order_id')->

                    leftJoin(DB_PREFIX . 'product_quote as pq', function ($join) {
                        $join->on('pq.order_id', '=', 'oco.order_id')->on('pq.product_id', '=', 'op.product_id');
                    })
                        ->where($mapProduct)->groupBy('as.order_id', 'as.product_id')->select('pq.price as pq_price', 'op.poundage', 'op.service_fee')
                        ->selectRaw('group_concat( distinct o.order_id) as order_id_list,group_concat( distinct o.id) as oid_list')->first();
                $new_res  = obj2array($new_res);
                $mapRma   = [
                    ['rp.product_id', '=', $product['product_id']],
                    ['r.order_id', '=', $product['order_id']],
                ];
                $rma_info = $this->orm->table(DB_PREFIX . 'yzc_rma_order as r')
                    ->leftJoin(DB_PREFIX . 'yzc_rma_order_product as rp', 'rp.rma_id', '=', 'r.id')->where($mapRma)->
                    selectRaw('group_concat( distinct r.rma_order_id) as rma_id_list,group_concat( distinct r.id) as rid_list')->first();
                $rma_info = obj2array($rma_info);
                if (null == $rma_info) {
                    $rma_info['rma_id_list'] = null;
                    $rma_info['rid_list']    = null;
                }
                if (null == $new_res) {

                    $new_res['order_id_list'] = null;
                    $new_res['oid_list']      = null;
                    $new_res['poundage']      = 0;
                }
                if ($country_id == 223 || $country_id == 107) {

                    if (isset($new_res['pq_price']) && $new_res['pq_price'] != null) {
                        $discount_price = sprintf('%.2f', $product['price'] - $new_res['pq_price']);
                        $real_price     = $product['price'];
                        $service_price  = 0.00;

                    } else {
                        $discount_price = 0.00;
                        $real_price     = $product['price'];
                        $service_price  = 0.00;
                    }
                    $sub_total            += $product['price'] * $product['quantity'];
                    $total_quote_discount += $discount_price * $product['quantity'];
                    $total_service_fee    += 0;
                    $total_transaction    += $new_res['poundage'];
                } elseif ($country_id == 222 || $country_id == 81) {

                    if (isset($new_res['service_fee']) && $new_res['service_fee'] != null) {
                        $service_price  = sprintf('%.2f', $new_res['service_fee'] / $product['quantity']);
                        $real_price     = $service_price + $product['price'];
                        $discount_price = 0.00;

                    } else {
                        $service_price          = 0.00;
                        $new_res['service_fee'] = $service_price;
                        $discount_price         = 0.00;
                        $real_price             = $product['price'];
                        $new_res['poundage']    = 0;

                    }
                    $sub_total            += $product['price'] * $product['quantity'];
                    $total_quote_discount += $discount_price * $product['quantity'];
                    $total_service_fee    += $new_res['service_fee'];
                    $total_transaction    += $new_res['poundage'];

                }

                //获取图片链接
                //获取这个订单的议价
                //获取这个订单的RMAID
                $this->load->model('tool/image');
                $image = $this->orm->table(DB_PREFIX . 'product')->where('product_id', $product['product_id'])->value('image');
                if ($image) {
                    $image = $this->model_tool_image->resize($image, 30, 30);
                } else {
                    $image = $this->model_tool_image->resize('placeholder.png', 30, 30);
                }
                //判断订金商品，显示原sku，增加seller的超链接
                if(isset($margin_process['advance_product_id']) && $margin_process['advance_product_id'] == $product['product_id']){
                    $margin_sku = $margin_process['original_sku'];
                    $margin_mpn = $margin_process['original_mpn'];
                    $margin_product_link = $this->url->link('product/product', 'product_id=' . $margin_process['original_product_id'], true);
                    $margin_seller_link = $this->url->link('customerpartner/profile', 'id=' . $margin_process['seller_id']);
                    $margin_seller_name = $margin_process['original_seller_name'];
                }
                $data['products'][] = array(
                    'name'             => $product['name'],
                    'img'              => $image,
                    'model'            => $product['model'],
                    'quantity'         => $product['quantity'],
                    'poundage'         => $this->currency->format($new_res['poundage'] + ($this->config->get('config_tax') ? $product['tax'] : 0), $order_info['currency_code'], $order_info['currency_value']),
                    'product_id'       => $product['product_id'],
                    'rma_id_list'      => explode(',', $rma_info['rma_id_list']),
                    'order_id_list'    => explode(',', $new_res['order_id_list']),
                    'oid_list'         => explode(',', $new_res['oid_list']),
                    'rid_list'         => explode(',', $rma_info['rid_list']),
                    'service_price'    => $this->currency->format($service_price + ($this->config->get('config_tax') ? $product['tax'] : 0), $order_info['currency_code'], $order_info['currency_value']),
                    'discount_price'   => $this->currency->format($discount_price + ($this->config->get('config_tax') ? $product['tax'] : 0), $order_info['currency_code'], $order_info['currency_value']),
                    'price'            => $this->currency->format($product['price'] + ($this->config->get('config_tax') ? $product['tax'] : 0), $order_info['currency_code'], $order_info['currency_value']),
                    'total'            => $this->currency->format(round($real_price, 2) * $product['quantity'] + ($this->config->get('config_tax') ? ($product['tax'] * $product['quantity']) : 0), $order_info['currency_code'], $order_info['currency_value']),
                    'reorder'          => $reorder,
                    'mpn'              => $product_info['mpn'],
                    'sku'              => $product_info['sku'],
                    'margin_sku'       => isset($margin_sku) ? $margin_sku : null,
                    'margin_mpn'       => isset($margin_mpn) ? $margin_mpn : null,
                    'margin_link'      => isset($margin_product_link) ? $margin_product_link : null,
                    'margin_seller_name' => isset($margin_seller_name) ? $margin_seller_name : null,
                    'margin_seller_link' => isset($margin_seller_link) ? $margin_seller_link : null,
                    // marketplace
                    'order_detail'     => str_replace('&amp;', '&', $this->url->link('account/customerpartner/order_detail', 'order_id=' . $order_info['order_id'] . '&product_id=' . $product['product_id'], true)),
                    'order_id'         => $product['order_id'],
                    // marketplace
                    'return'           => str_replace('&amp;', '&', $this->url->link('account/rma/purchaseorderrma/add', 'order_id=' . $order_info['order_id'] . '&product_id=' . $product['product_id'], true)),
                    // add by xxli reviewInfo
                    'can_return'       => $product['product_id'] != $this->config->get('signature_service_us_product_id'),
                    'customerName'     => $customerName['screenname'],
                    'order_product_id' => $product['order_product_id'],
                    'customer_id'      => $product_info['customer_id'],
                    'tag'              => $tags
                    // end xxli
                );

            }

            $total_price                  = sprintf('%.2f', $sub_total - $total_quote_discount + $total_transaction + $total_service_fee);
            $data['sub_total']            = $this->currency->format($sub_total, $this->session->data['currency']);
            $data['total_quote_discount'] = $this->currency->format($total_quote_discount, $this->session->data['currency']);
            $data['total_transaction']    = $this->currency->format($total_transaction, $this->session->data['currency']);
            $data['total_price']          = $this->currency->format($total_price, $this->session->data['currency']);
            $data['total_service_fee']    = $this->currency->format($total_service_fee, $this->session->data['currency']);
            $data['country_id']           = $country_id;


            $data['comment'] = htmlspecialchars(nl2br($order_info['comment']));

            // History
            $data['histories'] = array();

            $results = $this->model_account_order->getOrderHistories($order_id);

            foreach ($results as $result) {
                $data['histories'][] = array(
                    'date_added' => date($this->language->get('datetime_format'), strtotime($result['date_added'])),
                    'status'     => $result['status'],
                    'comment'    => $result['notify'] ? nl2br($result['comment']) : ''
                );
            }

            $data['continue'] = $this->url->link('account/order', '', true);
        } else {

        }

        end:
        $this->response->setOutput($this->load->view('account/product_quotes/margin/pay_proof', $data));
    }


    /**
     * Margin Transaction Details
     */
    public function account_check()
    {
        if (!$this->customer->isLogged()) {
            session()->set('redirect', $this->url->link('account/product_quotes/wk_quote_my', '', true));
            $this->response->redirect($this->url->link('account/login', '', true));
        }
        $customer_id = $this->customer->getId();
        $this->load->language('common/cwf');

        $data = [];
        $id   = $margin_agreement_id = intval(get_value_or_default($this->request->get, 'id', 0));


        $data['margin_agreement_id'] = $margin_agreement_id;
        $data['restOrderList']       = [];
        $data['total_pages']         = 0;
        $data['page_num']            = 1;
        $data['results']             = 0;

        if ($id > 0) {
            $page_num   = intval(get_value_or_default($this->request->get, 'page_num', 1));
            $page_limit = 100;




            $this->load->model('account/product_quotes/margin');


            $arr_sub_id = $this->model_account_product_quotes_margin->getSubPerformerList($margin_agreement_id);



            $param['margin_agreement_id'] = $margin_agreement_id;
            $param['page_num']            = $page_num;
            $param['page_limit']          = $page_limit;

            $margin_list = $this->model_account_product_quotes_margin->getMarginTransactionDetailsInfo($param);
            $total = $margin_list['count'];
            $rest_orders = $margin_list['ret'];
            if ($rest_orders) {
                $this->load->model('account/customerpartner');
                foreach ($rest_orders as $key => $rest_order) {
                    $self_buyer_id = $rest_order['buyer_id'];

                    $new_timezone              = changeOutPutByZone($rest_order['create_time'], $this->session);
                    $rest_orders[$key]['create_day']  = substr($new_timezone, 0, 10);
                    $rest_orders[$key]['create_hour'] = substr($new_timezone, 11);



                    $rma_qty = $this->model_account_product_quotes_margin->getRmaQty($rest_order['rest_order_id'], $rest_order['rest_product_id'], $self_buyer_id, $rest_order['sales_header_id']);
                    if ($rest_order['sales_header_id']) {
                        //已关联销售单
                        $connected_quantity = intval($rest_order['sales_quantity']) - intval($rma_qty);
                    } else {
                        //未关联销售单
                        //$connected_quantity = intval($rest_order['purchase_quantity']) - intval($rma_qty);
                        $connected_quantity = 'N/A';
                    }
                    $rest_orders[$key]['connected_quantity'] = $connected_quantity;



                    $rmaIDs                              = $this->model_account_customerpartner->getALLRMAIDSByOrderProduct($rest_order['rest_order_id'], $rest_order['rest_product_id'], $self_buyer_id);
                    $rest_orders[$key]['rma_ids']        = $rmaIDs;
                    $rest_orders[$key]['rest_order_url'] = ($customer_id == $rest_order['buyer_id']) ? ($this->url->link('account/order/purchaseOrderInfo', 'order_id=' . $rest_order['rest_order_id'], true)) : ('');
                }
                $data['restOrderList'] = $rest_orders;

            }

            $data['customer_id'] = $customer_id;
            $data['rma_url'] = str_replace('&amp;', '&', $this->url->link('account/rma_order_detail&rma_id=', '', true));
            //分页
            $total_pages         = ceil($total / $page_limit);
            $data['total_pages'] = $total_pages;
            $data['page_num']    = $page_num;
            $data['results']     = sprintf($this->language->get('text_pagination'), ($total) ? (($page_num - 1) * $page_limit) + 1 : 0, ((($page_num - 1) * $page_limit) > ($total - $page_limit)) ? $total : ((($page_num - 1) * $page_limit) + $page_limit), $total, $total_pages);

            //检查是否存在保证金商品调回记录
            $dispatch_records = $this->model_account_product_quotes_margin->getMarginDispatchBackRecord($id);
            if (!empty($dispatch_records)) {
                $this->load->model('account/order');
                foreach ($dispatch_records as $record) {
                    if (isset($record['in_seller_id'])) {
                        $original_store_name = $this->model_account_order->getCustomerName($record['in_seller_id']);
                        $original_store_link = $this->url->link('customerpartner/profile', 'id=' . $record['in_seller_id']);
                    }
                    if (isset($record['out_seller_id'])) {
                        $bond_store_name = $this->model_account_order->getCustomerName($record['out_seller_id']);
                        $bond_store_link = $this->url->link('customerpartner/profile', 'id=' . $record['out_seller_id']);
                    }
                    if ($record['adjustment_reason'] == 1) {
                        $reason = 'The buyer\'s reason';
                    } elseif ($record['adjustment_reason'] == 2) {
                        $reason = 'The store\'s reason';
                    } else {
                        $reason = 'Other';
                    }
                    $product_link = $this->url->link('product/product', 'product_id=' . $record['rest_product_id']);
                    $data['dispatch'][] = [
                        'bring_up_store' => isset($bond_store_name['screenname']) ? $bond_store_name['screenname'] : '',
                        'bring_in_store' => isset($original_store_name['screenname']) ? $original_store_name['screenname'] : '',
                        'bring_up_link' => isset($bond_store_link) ? $bond_store_link : 'javascript:void(0)',
                        'bring_in_link' => isset($original_store_link) ? $original_store_link : 'javascript:void(0)',
                        'sku' => $record['sku'],
                        'product_link' => $product_link,
                        'uncompleted_num' => $record['unaccomplished_num'],
                        'dispatch_num' => $record['adjust_num'],
                        'dispatch_time' => $record['create_time'],
                        'reason' => $reason
                    ];
                }
            }
        }

        $this->response->setOutput($this->load->view('account/product_quotes/margin/account_check', $data));
    }

    public function show_storage()
    {
        if (!$this->customer->isLogged()) {
            session()->set('redirect', $this->url->link('account/product_quotes/wk_quote_my', '', true));
            $this->response->redirect($this->url->link('account/login', '', true));
        }

        $data = [];
        $margin_id = $margin_agreement_id = intval(get_value_or_default($this->request->get, 'id', 0));

        $item_code = '';
        $sum_storage = 0;
        $length = 0;
        $width = 0;
        $height = 0;
        $margin_product_link = 'javascript:void(0)';

        $this->load->model('account/product_quotes/margin');
        $storage_records = $this->model_account_product_quotes_margin->getMarginStorageRecord($margin_id);

        if (!empty($storage_records)) {
            $current = current($storage_records);
            $combo_flag = $current->combo_flag;
            $rest_product_id = $current->rest_product_id;
            if($combo_flag){
                $sub_dimension = $this->model_account_product_quotes_margin->getComboProductSubDimension($rest_product_id);
                if(!empty($sub_dimension)){
                    foreach ($sub_dimension as $item) {
                        $data['sub_dimension'][] = [
                            'length' => number_format($item['length'], 2),
                            'width' => number_format($item['width'], 2),
                            'height' => number_format($item['height'], 2),
                        ];
                    }
                }
            }
            $length = $current->length;
            $width = $current->width;
            $height = $current->height;
            $item_code = $current->sku;

            $margin_product_link = $this->url->link('product/product', 'product_id=' . $current->margin_product_id);
            foreach ($storage_records as $record) {
                if (empty($record->storage_time)) {
                    continue;
                }
                $sum_storage += $record->storage_fee;
                $fee = $this->currency->formatCurrencyPrice($record->storage_fee, $this->session->data['currency']);
                $data['storage_record'][] = [
                    'storage_date' => $record->storage_time,
                    'storage_fee' => $fee,
                    'onhand_qty' => $record->onhand_qty
                ];
            }
        }
        $data['item_code'] = $item_code;
        $data['sum_storage'] = $this->currency->formatCurrencyPrice($sum_storage, $this->session->data['currency']);
        $data['length'] = number_format($length, 2);
        $data['width'] = number_format($width, 2);
        $data['height'] = number_format($height, 2);
        $data['margin_product_link'] = $margin_product_link;

        $this->response->setOutput($this->load->view('account/product_quotes/margin/bid_storage', $data));
    }

    //支付头款时，检查原商品库存数量
    public function checkStockNum()
    {
        $id = $margin_agreement_id = intval(get_value_or_default($this->request->get, 'id', 0));
        if ($id < 1) {
            $json['ret'] = 0;
            $json['msg'] = 'success';
            goto end;
        }

        $this->load->model('account/product_quotes/margin');
        $result = $this->model_account_product_quotes_margin->checkStockNum($margin_agreement_id);


        if ($result > 0) {
            $json['ret'] = 1;
            $json['msg'] = 'success';
        } else {
            $json['ret'] = 0;
            $json['msg'] = 'success';
        }


        end:
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }


    //判断购物车中，是否有保证金头款商品，有则判断原商品库存
    public function checkStockNumByCart()
    {
        $this->load->model('account/product_quotes/margin');
        $result = $this->model_account_product_quotes_margin->checkStockNumByCart();

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($result));
    }

    /**
     * 检测协议是否允许重新申请
     * @throws Exception
     */
    public function checkReapplied()
    {
        $this->language->load('account/product_quotes/margin');
        $this->load->model('account/product_quotes/margin');
        $this->load->model('account/product_quotes/margin_contract');

        $margin_id      = intval(get_value_or_default($this->request->post, 'margin_id', null));//主键
        $customer_id    = $this->customer->getId();

        if (!$this->customer->isLogged()) {
            $json['error'] = 'Please refresh the page.';
            goto end;
        }
        if ($this->customer->isPartner()) {
            $json['error'] = 'You are not buyer.';
            goto end;
        }
        if ((!request()->isMethod('POST'))) {
            $json['error'] = 'Please refresh the page.';
            goto end;
        }
        if (!isset($margin_id)) {
            $json['error'] = 'Please refresh the page.';
            goto end;
        }

        $marginInfo       = $this->model_account_product_quotes_margin->getInfo($margin_id);
        $buyer_id         = intval($marginInfo['buyer_id']);
        if (!$marginInfo) {
            $json['error'] = 'Please refresh the page.';
            goto end;
        }


        $ret = $this->model_account_product_quotes_margin->isCanReapplied($marginInfo);
        if($ret['ret'] == 0){
            $json['error'] = $ret['msg'];
            goto end;
        }

        // 如果 该sku 没有对应的模板存在 则给出提示
        $isExist = $this->model_account_product_quotes_margin->checkProductIsActiveInMarginTemplate($marginInfo['product_id']);
        if (!$isExist) {
            $json['error'] = 'This Item Code cannot be applied for margin agreement. Please contact with seller to argue.';
            goto end;
        }


        $json['success'] = 'Success';
        $json['redirect'] = str_replace('&amp;', '&', $this->url->link('account/product_quotes/margin/detail_list', 'id=' . $margin_id . '&actionother=reapplied', true));


        end:
        $this->response->returnJson($json);
    }

    /**
     * 检测所填的共同履约人是否已有有效绑定信息
     * @throws Exception
     */
    public function checkPerformerBindInfo()
    {
        $language = load()->language('account/product_quotes/margin');
        $marginId = $this->request->post('margin_id', 0);
        $performerCode = $this->request->post('performer_code', '');
        $marginInfo = app(MarginRepository::class)->getMarginAgreementInfo($marginId);
        if (customer()->isPartner()) {
            return $this->json(['code' => 0, 'error' => $language['error_margin_buyers_not_buyer']]);
        }
        if (!$marginInfo || $marginInfo['agreement_buyer_id'] !== customer()->getId()) {
            return $this->json(['code' => 0, 'error' => $language['text_performer_add_error']]);
        }
        if (empty($performerCode)) {
            return $this->json(['code' => 0, 'error' => $language['error_margin_performer_code_require']]);
        }
        $performerInfo = app(BuyerRepository::class)->getPerformerInfo($performerCode, $marginInfo['seller_id']);
        if (empty($performerInfo) || $performerInfo['is_partner'] == 1) {
            return $this->json(['code' => 0, 'error' => sprintf($language['error_margin_performer_not_exist'], $performerCode)]);
        }
        if ($performerInfo['status'] !== 1) {
            return $this->json(['code' => 0, 'error' => sprintf($language['error_margin_performer_disabled'], $performerCode)]);
        }
        if ($performerInfo['customer_id'] == customer()->getId() || $performerInfo['customer_id'] == $marginInfo['buyer_id']) {
            return $this->json(['code' => 0, 'error' => $language['error_margin_performer_illegal']]);
        }
        $isBinded = app(BuyerRepository::class)->checkBuyersIsBinded($marginInfo['buyer_id'], $performerInfo['customer_id']);
        if (!$isBinded) {
            return $this->json(['code' => 0, 'error' => sprintf($language['error_margin_buyers_no_binded'], $performerCode)]);
        }

        //#31737 免税Buyer和非免税Buyer不能参与同一个现货协议
        $vatTypeArr = Buyer::query()->whereIn('buyer_id', [$marginInfo['buyer_id'], $performerInfo['customer_id']])->get(['vat_type'])->pluck('vat_type')->all();
        if (count(array_unique($vatTypeArr)) > 1) {
            return $this->json(['code' => 0, 'error' => $performerCode . ' is different from you in applicable VAT policy. Please enter a partner account applicable to the same VAT policy as you.']);
        }

        return $this->json(['code' => 1, 'error' => 'ok']);
    }

    /**
     * 添加共同履约人
     * @throws Exception
     */
    public function performerAdd()
    {
        $this->language->load('account/product_quotes/margin');
        $this->load->model('account/product_quotes/margin');
        $this->load->model('account/product_quotes/margin_contract');

        $margin_id      = intval(get_value_or_default($this->request->post, 'margin_id', null));//主键
        $performer_code = trim(strval(get_value_or_default($this->request->post, 'performer_code', '')));
        $reason         = trim(strval(get_value_or_default($this->request->post, 'reason', '')));
        $customer_id    = $this->customer->getId();
        $country_id     = $this->customer->getCountryId();

        if (!$this->customer->isLogged()) {
            $json['error'] = 'Please refresh the page.';
            goto end;
        }
        if ($this->customer->isPartner()) {
            $json['error'] = 'You are not buyer.';
            goto end;
        }
        if ((!request()->isMethod('POST'))) {
            $json['error'] = 'Please refresh the page.';
            goto end;
        }
        if (!isset($margin_id)) {
            $json['error'] = 'Please refresh the page.';
            goto end;
        }
        if (utf8_strlen($performer_code) < 1) {
            $json['error'] = 'Enter email or buyer code...';
            goto end;
        }
        if (utf8_strlen($reason) < 1) {
            $json['error'] = 'REASON can not be left blank.';
            goto end;
        }
        if (utf8_strlen($reason) > 2000) {
            $json['error'] = 'REASON can not be more than 2000 characters.';
            goto end;
        }

        $marginInfo = $this->model_account_product_quotes_margin->getInfo($margin_id);
        if (!$marginInfo) {
            $json['error'] = $this->language->get('text_performer_add_error');
            goto end;
        }
        $seller_id = intval($marginInfo['seller_id']);
        $buyer_id = intval($marginInfo['buyer_id']);
        $agreement_id = strval($marginInfo['agreement_id']);
        $product_id = intval($marginInfo['product_id']);
        if ($buyer_id != $customer_id) {
            $json['error'] = $this->language->get('text_performer_add_error');
            goto end;
        }


        //检查是否有共同履约人 协议从属用户
        $sql = "SELECT c.email, c.user_number
    FROM tb_sys_margin_performer_apply AS mpa
    LEFT JOIN oc_customer AS c ON c.customer_id=mpa.performer_buyer_id
    WHERE mpa.agreement_id='{$margin_id}'
        AND mpa.check_result IN (0, 1) AND mpa.seller_approval_status IN (0,1)";

        $isHavePerformer = $this->db->query($sql)->row;
        if ($isHavePerformer) {
            //xxx已被添加成为共同履约人 ，不能再次添加
            $json['error'] = $isHavePerformer['email'] . ' has already been added to be your agreement partner sucessfully. <br>Please don not add again.';
            goto end;
        }



        //输入的共同履约人的信息
        $sql = "SELECT
        DISTINCT c.customer_id
        ,c.*
        , b2s.id AS b2s_id
        , c2c.is_partner
        , b2s.buy_status
        , b2s.price_status
        , b2s.buyer_control_status
        , b2s.seller_control_status
    FROM oc_customer AS c
    LEFT JOIN oc_customerpartner_to_customer AS c2c ON c2c.customer_id=c.customer_id
    LEFT JOIN oc_buyer_to_seller AS b2s ON b2s.buyer_id=c.customer_id AND b2s.seller_id={$seller_id}
    WHERE (c.email='{$performer_code}' OR c.user_number='{$performer_code}')";

        $performer_info = $this->db->query($sql)->row;
        if (!$performer_info) {
            $json['error'] = 'Cannot find the account ' . $performer_code . ', please enter a right partner account. ';
            goto end;
        }
        if ($performer_info['customer_id'] == $customer_id || $performer_info['customer_id'] == $buyer_id) {
            //添加共同履约人时，添加的账户为自己
            $json['error'] = 'The current account cannot be added as an agreement partner of the  margin agreement.';
            goto end;
        }
        if ($performer_info['status'] != 1) {
            //xxx 用户不是Buyer
            $json['error'] = $performer_code . ' is a disabled acount, please enter a valid partner account.';
            goto end;
        }
        if ($performer_info['is_partner'] == 1) {
            $json['error'] = 'Cannot find the account ' . $performer_code . ', please enter a right partner account. ';
            goto end;
        }
        if($performer_info['country_id'] != $country_id){
            //国别不同
            $json['error'] = $performer_code . ' is an account in another country market. It cannot be added as your agreement partner.';
            goto end;
        }
        if (is_null($performer_info['b2s_id'])) {
            //Buyer与Seller未建立联系
            $json['error'] = $performer_code . ' has not connected with the seller, please contact the seller.';
            goto end;
        } else {
            if ($performer_info['buy_status'] === 0) {
                //xxx 用户与店铺建立联系，但是不能购买，请联系店铺
                $json['error'] = $performer_code . ' has connected with this seller but is still not able to purchase products. Please contact the seller.';
                goto end;
            }
            if ($performer_info['price_status'] === 0) {
                //xxx 用户与店铺建立联系，但是不可见价格，请联系店铺
                $json['error'] = $performer_code . ' has connected with this seller but is still not able to purchase products. Please contact the seller.';
                goto end;
            }
            if ($performer_info['buyer_control_status'] === 0) {
                //履约人把Seller拉黑了。请联系 履约人
                $json['error'] = $performer_code . ' has not connected with the seller.';
                goto end;
            }
            if ($performer_info['seller_control_status'] === 0) {
                //xxx 用户已被店铺添加至黑名单，请联系店铺
                $json['error'] = $performer_code . ' cannot purchase this product. Please contact the seller.';
                goto end;
            }
        }

        //精细化不可见
        $this->load->model('customerpartner/DelicacyManagement');
        $delicacy_model = $this->model_customerpartner_DelicacyManagement;
        if (!$delicacy_model->checkIsDisplay($product_id, $performer_info['customer_id'])) {
            $json['error'] = $performer_code . ' has connected with this seller but is still not able to purchase products. Please contact the seller.';
            goto end;
        }


        $ret = $this->model_account_product_quotes_margin->isCanPerformerAdd($marginInfo);
        if($ret['ret'] == 0){
            $json['error'] = $ret['msg'];
            goto end;
        }

        //是否绑定
        $isBinded = app(BuyerRepository::class)->checkBuyersIsBinded($buyer_id, $performer_info['customer_id']);
        if (!$isBinded) {
            $json['error'] = sprintf($this->language->get('error_margin_buyers_no_binded'), $performer_code);
            goto end;
        }

        //#31737 免税Buyer和非免税Buyer不能参与同一个现货协议
        $vatTypeArr = Buyer::query()->whereIn('buyer_id', [$buyer_id, $performer_info['customer_id']])->get(['vat_type'])->pluck('vat_type')->all();
        if (count(array_unique($vatTypeArr)) > 1) {
            $json['error'] = $performer_code . ' is different from you in applicable VAT policy. Please enter a partner account applicable to the same VAT policy as you.';
            goto end;
        }

        $ret = $this->model_account_product_quotes_margin->performerAdd($marginInfo, $performer_info, $reason);
        $json['success'] = 'Your Add a Partner request has been submitted successfully and is currently being processed. ';


        end:
        $this->response->returnJson($json);
    }
}
