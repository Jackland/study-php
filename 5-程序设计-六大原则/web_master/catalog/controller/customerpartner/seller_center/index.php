<?php

use App\Catalog\Controllers\AuthSellerController;
use App\Components\Storage\StorageCloud;
use App\Enums\Buyer\BuyerType;
use App\Repositories\Buyer\BuyerRepository;
use App\Repositories\Buyer\BuyerToSellerRepository;
use App\Repositories\Buyer\BuyerUserPortraitRepository;
use App\Repositories\Buyer\BuySellerRecommendRepository;
use App\Repositories\Margin\AgreementRepository;
use App\Repositories\Seller\SellerRepository;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Class ControllerCustomerpartnerSellerCenterIndex
 * @property ModelCustomerpartnerSellerCenterIndex model_customerpartner_seller_center_index
 * @property ModelAccountCustomerpartner model_account_customerpartner
 * @property ModelCustomerpartnerStoreRate model_customerpartner_store_rate
 * @property ModelNoticeNotice model_notice_notice
 * @property ModelMessageMessage model_message_message
 * @property ModelAccountProductQuotesMarginContract model_account_product_quotes_margin_contract
 * @property ModelAccountProductQuoteswkproductquotes model_account_product_quotes_wk_product_quotes
 * @property ModelAccountProductQuotesRebatesContract model_account_product_quotes_rebates_contract
 * @property ModelFuturesAgreement model_futures_agreement
 * @property ModelToolImage model_tool_image
 * @property ModelAccountOrder model_account_order
 * @property ModelCatalogProduct model_catalog_product
 * @property ModelAccountProductQuotesRebatesAgreement model_account_product_quotes_rebates_agreement
 * @property ModelAccountCustomerpartnerMarginOrder model_account_customerpartner_margin_order
 * @property ModelCustomerpartnerProductManage $model_customerpartner_product_manage
 */
class ControllerCustomerpartnerSellerCenterIndex extends AuthSellerController
{
    const PRODUCT_TYPE_GENERAL = 1;
    const PRODUCT_TYPE_COMBO = 2;
    const PRODUCT_TYPE_LTL = 4;
    const PRODUCT_TYPE_PART = 8;

    private $customer_id;
    private $country_id;
    // 用来控制是否显示个人中心顶部云送仓提醒
    private $isTopCwfNoticeKey = 'is_top_cwf_notice';

    /**
     * ControllerCustomerpartnerSellerCenterIndex constructor.
     * @param Registry $registry
     * @throws Exception
     */
    public function __construct(Registry $registry)
    {
        //判断登录情况
        parent::__construct($registry);

        //初始化添加样式
        $this->document->addStyle('catalog/view/javascript/product/element-ui.css');
        $this->document->addScript('catalog/view/javascript/product/element-ui.js');

        //初始化時自動加載
        $this->load->language('customerpartner/seller_center/index');
        $this->load->model('customerpartner/seller_center/index');

        $this->customer_id = $this->customer->getId();
        $this->country_id = $this->customer->getCountryId();

        //面包屑导航
        $this->crumbs = [
            [
                'text' => $this->language->get('text_home'),
                'href' => $this->url->link('common/home', '', true),
                'separator' => false
            ],
            [
                'text' => 'Seller Central',
                'href' => 'javascript:void(0);'
            ],
        ];

    }

    /**
     * @return array
     */
    public function page_data()
    {
        //页面框架数据
        $page_data = [];
        session()->set('marketplace_separate_view', 'separate');
        $page_data['separate_view'] = true;
        $page_data['column_left'] = '';
        $page_data['column_right'] = '';
        $page_data['content_top'] = '';
        $page_data['content_bottom'] = '';
        return $page_data;
    }

    public function index()
    {
        $data = [];
        $this->setDocumentInfo(__('个人中心', [], 'catalog/document'));
        $data['breadcrumbs'] = $this->getBreadcrumbs();

        {    //页面信息
            $session = $this->session->data;
            $data['success'] = $session['success'] ?? '';
            if (isset($session['success'])) {
                $this->session->remove('success');
            }
            $data['error_warning'] = $session['error_warning'] ?? '';
            if (isset($session['error_warning'])) {
                $this->session->remove('error_warning');
            }
            //处理顶部增加云送仓发货运费使用上限提醒
            $data['is_top_cwf_notice'] = false;
            if(app(SellerRepository::class)->isShowCwfNotice()){
                //目前美国外部seller才显示
                $data['is_top_cwf_notice'] = (boolean)($this->session->get($this->isTopCwfNoticeKey,1));
                $data['cwf_info_id'] = $this->config->get('cwf_help_id');
            }
            //页面框架数据
            $data = array_merge($data, $this->page_data());
        }
        {//数据
            $data['currency'] = $this->session->get('currency');
            //店铺信息
            $data['store_info'] = $this->basicStoreInformation();
            //账户经理
            $width = 70;//width:height=1:1.4
            $height = 98;
            $data['account_manage'] = $this->model_customerpartner_seller_center_index->accountManager(intval($this->customer_id));
            if(!empty($data['account_manage']) && isset($data['account_manage']['path']) && !empty($data['account_manage']['path'])){
                $data['account_manage_pic'] = StorageCloud::root()->getUrl($data['account_manage']['path'], ['w' => $width, 'h' => $height]);
            }else{
                $data['account_manage_pic'] = $this->model_tool_image->resize('manager.png', $width, $height);
            }
            //待处理的 Bid
            $data['bid'] = $this->bidData();
            //今日交易额与交易数量　
            $data['today'] =$this->sellData(date('Y-m-d', time()) . ' 00:00:00',date('Y-m-d H:i:s', time()));
            $data['order_history_link'] = $this->url->link('account/customerpartner/orderlist', '', true);
            //待处理的RMA数据
            $data['rma'] = $this->rmaData();
            //今日产品下载量
            $data['product_download'] = $this->model_customerpartner_seller_center_index->productDownload($this->customer_id, date('Y-m-d', time()) . ' 00:00:00');
            //低库存产品
            $data['low_product'] = $this->countLowInventory();
            //产品销量排名
            $data['sale_rank_one'] = $this->productSaleRank(date('Y-m-d', strtotime("-1 month +1days")).' 00:00:00');
            $data['sale_rank_nine'] = $this->productSaleRank(date('Y-m-d', strtotime("-3 month +1days")).' 00:00:00');
            //促销活动
            $data['marketing_campaign'] = $this->marketingCampaign();
            //未读消息
            $data['unread_message'] = $this->unreadMessage();
            //最近公告信息
            $data['marketplace_notification'] = $this->maketplaceNotifications();
            //有评分显示评分详细数据
            if ($data['store_info']['comprehensive_total'] != '--') {
                // 评分数据
                $data['comprehensive_info'] = $this->comprehensiveInfo();
            }
            // 推荐信息
            $data['recommend_data'] = $this->recommendData();
        }

        //输出
        return $this->render('customerpartner/seller_center/index', $data, [
            'separate_column_left' => 'account/customerpartner/column_left',
            'header' => 'account/customerpartner/header',
            'footer' => 'account/customerpartner/footer',
        ]);
    }

    /**
     * 关闭seller 个人中心顶部云送仓提醒
     * @return JsonResponse
     */
    public function closeSellerCenterCwfNotice()
    {
        $this->session->set($this->isTopCwfNoticeKey, 0);
        return $this->jsonSuccess();
    }

    /**
     * 综合评分明细
     * @return array
     */
    public function comprehensiveInfo()
    {
        //综合得分(综合评分)
        return $this->model_customerpartner_seller_center_index->comprehensiveSellerData($this->customer_id,$this->country_id, 1);
    }

    /**
     * @return JsonResponse
     * @throws Exception
     */
    public function ninetySell()
    {
        set_time_limit(0);
        $now_date = date('Y-m-d H:i:s', time());
        $start_date = date('Y-m-d', strtotime("-90 days")) . ' 00:00:00';
        $data = $this->sellData($start_date, $now_date);
        return $this->json($data);
    }

    /**
     * @param string $filter_date_from 'Y-m-d H:i:s'
     * @param string $filter_date_to 'Y-m-d H:i:s'
     * @return array
     * @throws Exception
     */
    public function sellData($filter_date_from,$filter_date_to)
    {
        $this->load->model('account/customerpartner');
        $this->load->language('account/customerpartner/orderlist');
        $this->load->model('account/product_quotes/margin_contract');
        $margin_store_id = array();
        //根据指定用户（国别信息）获得保证金需求的服务店铺sellerid
        $service_id = $this->model_account_product_quotes_margin_contract->getMarginServiceStoreId($this->customer->getId());

        //根据指定用户（国别信息）获得保证金需求的包销店铺sellerid
        $bx_id = $this->model_account_product_quotes_margin_contract->getMarginBxStoreId($this->customer->getId());

        if(isset($service_id)){
            $margin_store_id[] = $service_id;
        }
        if(isset($bx_id)){
            $margin_store_id[] = $bx_id;
        }

        //有保证金采购订单号
        $margin_order_list = $this->model_account_product_quotes_margin_contract->getMarginOrderIdBySellerId($this->customer->getId());
        $data['margin_order_list'] = $margin_order_list;

        //根据采购订单号，查询相关联的保证金协议信息
        $order_seller_id = $this->customer->getId();
        $margin_agreement = $this->model_account_product_quotes_margin_contract->getSellerMarginByOrderId([], $order_seller_id, true);
        $process_product = array();
        if (!empty($margin_agreement)) {
            $agreement_ids = [];
            foreach ($margin_agreement as $agreement) {
                if ($agreement['seller_id'] == $order_seller_id && !in_array($agreement['agreement_id'], $agreement_ids)) {
                    $agreement_ids[] = $agreement['agreement_id'];
                }
            }
            // 批量获取保证金协议进度信息
            $ge = $this->model_account_product_quotes_margin_contract->getMarginProcessDetailByAgreementIds($agreement_ids);
            if ($ge) {
                foreach ($ge as $item) {
                    if (!empty($item->advance_product_id) && !in_array($item->advance_product_id, $process_product)) {
                        $process_product[] = $item->advance_product_id;
                    }
                    if (!empty($item->rest_product_id) && !in_array($item->rest_product_id, $process_product)) {
                        $process_product[] = $item->rest_product_id;
                    }
                }
            }
        }
        $data = array(
            'filter_date_from' =>  $filter_date_from,
            'filter_date_to' =>  $filter_date_to,
            'customer_id' => $this->customer->getId(),
            'filter_include_all_refund' => 1,
            'margin_store_id' => $margin_store_id,
            'margin_order_list' => $margin_order_list,
            'bx_product_id' => $process_product,
        );

        //是否启用议价
        $enableQuote = false;
        if (!empty($this->customer->getCountryId()) && in_array($this->customer->getCountryId(), QUOTE_ENABLE_COUNTRY)) {
            $enableQuote = true;
        }

        //是否为欧洲
        $isEurope = false;
        if ($this->country->isEuropeCountry($this->customer->getCountryId())) {
            $isEurope = true;
        }

        // 过滤补运费产品
        $data['filter_fill_freight_product'] = 1;

        $orders = $this->model_account_customerpartner->getSellerOrdersForUpdate($data);
        //12591 B2B记录各国别用户的操作时间
        $order_total = [];
        $totalPrice = 0.00;
        $totalNumber = 0;
        if ($orders && $orders instanceof Generator) {
            $index = 0;
            foreach ($orders as $detail) {
                $detail = get_object_vars($detail);
                $isEurope && $content[$index][] = $detail['service_fee_per'];
                $enableQuote && $content[$index][] = -$detail['amount_price_per'];
                $enableQuote && $isEurope && $content[$index][] = -bcmul($detail['amount_service_fee_per'], 1, 2);
                $totalNumber += $detail['quantity'];
                $totalPrice += ((double)$detail['quantity'] * $detail['SalesPrice'] + $detail['serviceFee'] + ($detail['freight_per'] + $detail['package_fee']) * $detail['quantity'] - $detail['quote'] - (double)$detail['campaign_amount']);
                array_push($order_total, $detail['orderId']);
                $index++;
            }
        }
        $totalPrice = round($totalPrice, $this->customer->isJapan() ? 0 : 2);
        return [
            'totalOrder' => count(array_unique($order_total)),
            'totalNumber' => $totalNumber,
            'totalPrice' => $totalPrice
        ];
    }

    /**
     * 低库存数量
     * @return mixed
     * @throws Exception
     */
    public function countLowInventory()
    {
        $this->load->model('customerpartner/product_manage');
        $filterProductType = self::PRODUCT_TYPE_GENERAL + self::PRODUCT_TYPE_COMBO + self::PRODUCT_TYPE_LTL + self::PRODUCT_TYPE_PART;
        $data['low_product_total'] = $this->model_customerpartner_product_manage->querySellerProductNum([
            'filter_available_qty' => 10, //可用（上架）数量（小于10，包含等于0）
            'filter_status' => 1, //上架
            'filter_product_type' => intval($filterProductType), //商品类型
            'filter_buyer_flag' => 1,  //可独立售卖
        ], $this->customer_id);
        $data['lack_product_total'] = $this->model_customerpartner_product_manage->querySellerProductNum([
            'filter_available_qty' => 0, //可用（上架）数量（等于0）
            'filter_status' => 1, //上架
            'filter_product_type' => intval($filterProductType), //商品类型
            'filter_buyer_flag' => 1, //可独立售卖
        ], $this->customer_id);
        if ($this->country_id == AMERICAN_COUNTRY_ID) {
            $data['low_lack_link'] = $this->url->to('customerpartner/warehouse/inventory');
        } else {
            $data['low_lack_link'] = $this->url->link('account/customerpartner/product_manage', '', true);
        }

        return $data;
    }

    /**
     * RMA待处理
     * @return array
     * @throws Exception
     */
    public function rmaData()
    {
        $data['count'] = $this->model_customerpartner_seller_center_index->getNoHandleRmaCount($this->customer_id);
        $data['rma_link'] = $this->url->link('account/customerpartner/rma_management', '', true);
        return $data;
    }

    /**
     * 待处理的 Bid
     * @return array
     * @throws Exception
     */
    public function bidData()
    {
        $this->load->model('account/product_quotes/wk_product_quotes');
        $this->load->model('account/product_quotes/margin_contract');
        $this->load->model('account/product_quotes/rebates_contract');
        $this->load->model('account/product_quotes/rebates_contract');
        $this->load->model('futures/agreement');
        $data['spot_num'] = $this->model_account_product_quotes_wk_product_quotes->quoteAppliedCount($this->customer_id);
        $data['spot_num_link'] = $this->url->link('account/customerpartner/wk_quotes_admin', '', true);
        $data['rebates_num'] = $this->model_account_product_quotes_rebates_contract->rebatesAppliedCount($this->customer_id);
        $data['margin_num'] = app(AgreementRepository::class)->sellerMarginBidsHotspotCount($this->customer_id);
        $data['future_num'] = $this->model_futures_agreement->sellerAgreementTotal($this->customer_id);
        return $data;
    }

    /**
     * 店铺信息
     * @return array
     * @throws Exception
     */
    public function basicStoreInformation()
    {
        //seller名、店铺名、头像
        $this->load->model('account/customerpartner');
        $sellerProfile = $this->model_account_customerpartner->getProfile();
        $data['firstname'] = $sellerProfile['firstname'] ?? '';
        $data['lastname'] = $sellerProfile['lastname'] ?? '';
        $data['screenname'] = $sellerProfile['screenname'] ?? '';
        $data['store_code'] = trim($sellerProfile['firstname']) . trim($sellerProfile['lastname']);
        $this->load->model('tool/image');
        if (isset($sellerProfile['avatar']) && $sellerProfile['avatar']) {
            $data['image'] = $this->model_tool_image->resize($sellerProfile['avatar'], 45, 45);
        } else {
            $data['image'] = '/image/catalog/Logo/yzc_logo_45x45.png';
        }
        $data['profile_link'] = $this->url->link('account/customerpartner/profile', '', true);

        $this->load->model('customerpartner/store_rate');
        //店铺退返率标签
        $data['return_rate'] = $this->model_customerpartner_store_rate->returnsMarkByRate($sellerProfile['returns_rate'] ?? null);
        //店铺回复率标签
        $data['response_rate'] = $this->model_customerpartner_store_rate->responseMarkByRate($sellerProfile['response_rate'] ?? null);
        //退货同意率
        $return_approval_rate = $this->model_customerpartner_seller_center_index->returnApprovalRate([$this->customer_id]);

        //3个月内是否为外部新seller
        $data['is_out_new_seller'] = app(SellerRepository::class)->isOutNewSeller($this->customer_id, 3);
        //评分
        $task_info = $this->model_customerpartner_seller_center_index->getSellerNowScoreTaskNumberEffective(intval($this->customer_id));
        $data['comprehensive_total'] = isset($task_info['performance_score']) ? number_format(round($task_info['performance_score'], 2), 2) : '--';

        $data['new_seller_score'] = false;
        //无评分 且 在3个月内是外部新seller
        if (!isset($task_info['performance_score']) && $data['is_out_new_seller']) {
            $data['new_seller_score'] = true;
        }

        $data['comprehensive_end_date']=isset($task_info['score_task_number']) ? date('Y-m-d', strtotime($task_info['score_task_number'])) : '--';
        //seller评分说明页
        if (ENV_DROPSHIP_YZCM == 'dev_35') {
            $information_id = 131;
        } elseif (ENV_DROPSHIP_YZCM == 'dev_17') {
            $information_id = 130;
        } elseif (ENV_DROPSHIP_YZCM == 'pro') {
            $information_id = 133;
        } else {
            $information_id = 133;
        }
        $data['comprehensive_url'] = $this->url->link('information/information', ['information_id' => $information_id]);
        $data['return_approval_rate'] = $return_approval_rate[$this->customer_id] ?? 0;
        return $data;
    }

    /**
     * 销售量排行
     * @param string $filter_date_from 'Y-m-d H:i:s'
     * @return array
     */
    public function productSaleRank($filter_date_from)
    {
        //产品销售排行
        $filter['filter_date_from'] = $filter_date_from;
        $filter['filter_order'] = 'DESC';
        $filter['filter_page'] = 0;
        $filter['filter_limit'] = 6;
        $data = $this->model_customerpartner_seller_center_index->productSaleRank($this->customer_id, $filter);
        foreach ($data as $key => $val) {
            $data[$key]['productPreviewLink'] = $this->url->link('product/product', 'product_id=' . $val['product_id'] . "&product_token=" . ($this->session->data['product_token'] ?? ''), true);
        }
        return $data;
    }

    /**
     * 促销活动
     * @return array
     */
    public function marketingCampaign()
    {
        $marketing_campaign = $this->model_customerpartner_seller_center_index->marketingCampaign($this->country_id, 2);
        foreach ($marketing_campaign['list'] as $key => $val) {
            $marketing_campaign['list'][$key]['name'] = $val['seller_activity_name'] ?? $val['name'];
            $marketing_campaign['list'][$key]['effective_time'] = changeOutPutByZone($val['effective_time'],$this->session,'Y-m-d');
            $marketing_campaign['list'][$key]['expiration_time'] = changeOutPutByZone($val['expiration_time'],$this->session,'Y-m-d');
            if (
                !is_string($val['effective_time']) ||
                !isset($this->session->data['country']) ||
                !in_array($this->session->data['country'], CHANGE_TIME_COUNTRIES)
            ) {
                $marketing_campaign['list'][$key]['effective_time'] =date('Y-m-d',strtotime($val['effective_time']));
                $marketing_campaign['list'][$key]['expiration_time'] =date('Y-m-d',strtotime($val['expiration_time'])) ;
            }

            $marketing_campaign['list'][$key]['register_link'] = $this->url->link('customerpartner/marketing_campaign/request', ['id' => $val['id']], true);
        }
        $marketing_campaign['index_link'] = $this->url->link('customerpartner/marketing_campaign/index/activity#proEvents', '', true);
        return $marketing_campaign;
    }

    /**
     * 未读消息
     * @return array
     * @throws Exception
     */
    public function unreadMessage()
    {
        $this->load->model('message/message');
        $data['from_buyers'] = $this->model_message_message->unReadMessageCount($this->customer_id);
        $system_unread_by_type = $this->model_message_message->unReadSystemMessageCount($this->customer_id);
        $data['system_alters'] = intval($system_unread_by_type['000']);
        $data['customer_service_reply'] = $this->model_message_message->unReadTicketCount($this->customer_id);
        $data['from_buyers_link'] = $this->url->to('customerpartner/message_center/my_message/buyers');
        $data['system_alters_link'] = $this->url->to('customerpartner/message_center/my_message/system');
        $data['customer_service_reply_link'] = $this->url->link('account/ticket/lists', '', true);
        return $data;
    }

    /**
     * 最近公告信息
     * @return array
     * @throws Exception
     */
    public function maketplaceNotifications()
    {
        $this->load->model('notice/notice');
        $data['notices'] = $this->model_notice_notice->listColumnNotice($this->customer_id, $this->country_id, 1, 4);
        $data['notice_action'] = $this->url->to('customerpartner/message_center/my_message/notice');
        $data['notice_form_action'] = $this->url->link('information/notice/getForm', '', true);
        return $data;
    }

    /**
     * 推荐信息
     * @return array
     */
    private function recommendData()
    {
        $recommends = app(BuySellerRecommendRepository::class)->getLastBatchBySeller($this->customer_id, [
            'buyer',
            'buyerCustomer',
            'buyer.userPortrait',
        ]);
        $data = [];
        $buyerRepo = app(BuyerRepository::class);
        $buyerSellerRepo = app(BuyerToSellerRepository::class);
        $buyerUserPortraitRepo = app(BuyerUserPortraitRepository::class);
        $buyerTypes = $buyerRepo->getTypesByIds($recommends->pluck('buyer_id')->toArray());
        foreach ($recommends as $recommend) {
            $formatted = $buyerUserPortraitRepo->formatUserPortrait($recommend->buyer->userPortrait, [
                'main_category_id' => 'main_category',
            ]);
            $data[] = [
                'id' => $recommend->id,
                'buyer_id' => $recommend->buyer_id,
                'buyer_number' => $recommend->buyerCustomer->user_number,
                'buyer_name' => $recommend->buyerCustomer->full_name,
                'match_score' => $recommend->match_score,
                'type_name' => BuyerType::getDescription($buyerTypes[$recommend['buyer_id']]),
                'main_category_name' => $formatted['main_category'],
                'last_translation_date' => $buyerSellerRepo->getLastCompleteTransactionOrderDate($this->customer_id, $recommend->buyer_id),
            ];
        }
        return $data;
    }
}
