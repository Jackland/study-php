<?php
/**
 * Create by PHPSTORM
 * User: yaopengfei
 * Date: 2020/7/1
 * Time: 下午12:50
 */

use App\Catalog\Search\Margin\MarginAgreementSearch;
use App\Catalog\Search\Message\InboxSearch;
use App\Catalog\Search\Safeguard\SafeguardClaimSearch;
use App\Components\Storage\StorageCloud;
use App\Enums\Message\MsgDeleteStatus;
use App\Enums\Message\MsgReceiveSendType;
use App\Helper\CountryHelper;
use App\Repositories\Calendar\CalendarReminderRepository;
use App\Repositories\Message\NoticeRepository;
use App\Repositories\Message\StationLetterRepository;
use App\Repositories\Message\StatisticsRepository as MessageStatisticsRepositoryAlias;
use Catalog\model\account\sales_order\SalesOrderManagement as sales_model;

/**
 * @property ModelAccountCustomerpartner $model_account_customerpartner
 * @property ModelAccountBuyerCentral $model_account_buyer_central
 * @property ModelAccountCustomerOrder $model_account_customer_order
 * @property ModelAccountProductQuotesMargin $model_account_product_quotes_margin
 * @property ModelFuturesAgreement $model_futures_agreement
 * @property ModelAccountRmaManagement $model_account_rma_management
 * @property ModelMessageMessage $model_message_message
 * @property ModelAccountTicket $model_account_ticket
 * @property ModelNoticeNotice $model_notice_notice
 * @property ModelToolImage $model_tool_image
 * @property ModelAccountCustomerOrderImport $model_account_customer_order_import
 * @property ModelAccountMappingManagement $model_account_mapping_management
 * @property ModelCustomerpartnerSellerCenterIndex model_customerpartner_seller_center_index
 *
 * Class ControllerAccountBuyerCentral
 */
class ControllerAccountBuyerCentral extends Controller
{
    const TB_SYS_CUSTOMER_SALES_ORDER_STATUS_INIT = 0;
    const TB_SYS_CUSTOMER_SALES_ORDER_STATUS_TO_BE_PAID = 1;
    const TB_SYS_CUSTOMER_SALES_ORDER_STATUS_LTL_TO_EMAIL = 64;
    const TB_SYS_CUSTOMER_SALES_ORDER_STATUS_BEING_PROCESSED = 2;

    const TB_SYS_CUSTOMER_SALES_ORDER_TRACKING_ALL = 2;
    const TB_SYS_CUSTOMER_SALES_ORDER_TRACKING_YES = 1;

    const VW_RMA_ORDER_INFO_STATUS_APPLIED = 1;
    const VW_RMA_ORDER_INFO_STATUS_PENDING = 3;

    private $customer_id;

    private $sales_model;

    /**
     * ControllerAccountBuyerCentral constructor.
     * @param $registry
     * @throws Exception
     */
    public function __construct($registry)
    {
        parent::__construct($registry);

        if (!$this->customer->isLogged()) {
            session()->set('redirect', $this->url->link('account/buyer_central', '', true));
            $this->response->redirect($this->url->link('account/login', '', true));
        }

        $this->load->model('account/customerpartner');
        if ($this->model_account_customerpartner->chkIsPartner() || (isset($this->session->data['marketplace_seller_mode']) && !$this->session->data['marketplace_seller_mode'])) {
            $this->response->redirect($this->url->link('account/account', '', true));
        }

        $this->customer_id = intval($this->customer->getId());

        $this->load->language('account/buyer_central');

        $this->load->model('account/buyer_central');

        $this->sales_model = new sales_model($registry);
    }

    /**
     * buyer的个人中心
     * @throws ReflectionException
     * @throws Exception
     */
    public function index()
    {
        $this->document->setTitle($this->language->get('heading_title'));
        $data['breadcrumbs'] = [
            [
                'text' => $this->language->get('text_home'),
                'href' => $this->url->link('common/home')
            ],
            [
                'text' => $this->language->get('heading_title'),
                'href' => $this->url->link('account/buyer_central', '', true)
            ]
        ];
        $data['menu_name_links'] = $this->getMenuNameLinks();
        $data['buyer_info'] = $this->getBuyerInfo();
        $data['leasing_manager']=$this->leasingManagerInfo();
        $data['buyer_sale_order_and_bid_statistics'] = $this->getBuyerSaleOrderAndBidStatistics();
        $data['sale_rankings'] = $this->getBuyerSalesRankings();
        $promotionActivities = $this->getPromotionActivities();
        $data['promotion_activities'] = array_slice($promotionActivities, 0, 2);
        $data['promotion_activities_count'] = count($this->getPromotionActivities());
        $data['view_all_promotion_activity_link'] = $this->url->link('common/home');
        $data['messages'] = $this->getUnreadMessages();
        $data['notices'] = $this->getNotices();

        $country = session('country', 'USA');
        $fromZone = CountryHelper::getTimezoneByCode('USA');
        $toZone = CountryHelper::getTimezoneByCode($country);
        $data['country'] = $country;
        $result = app(CalendarReminderRepository::class)->getRemainderCenter($fromZone, $toZone, true);
        $data['objCollectionWeek'] = json_encode($result['objCollectionWeek']);
        $data['listCollectionMonth'] = json_encode($result['listCollectionMonth']);

        //综合得分(综合评分)
        $this->load->model('customerpartner/seller_center/index');
        $task_info=$this->model_customerpartner_seller_center_index->getBuyerNowScoreTaskNumberEffective($this->customer_id);
        $data['comprehensive_total']=isset($task_info['performance_score'] ) ? number_format(round($task_info['performance_score'],2),2): '--';
        $data['comprehensive_end_date']=isset($task_info['score_task_number']) ? date('Y-m-d', strtotime($task_info['score_task_number'])) : '--';
        //综合得分(综合评分)
        $comprehensiveInfo = $this->model_customerpartner_seller_center_index->comprehensiveSellerData(intval($this->customer->getId()),intval($this->customer->getCountryId()),2);
        $data['comprehensiveInfo'] = $comprehensiveInfo;
        //充值流程说明页
        if (ENV_DROPSHIP_YZCM == 'dev_35') {
            $information_id=132;
        } elseif (ENV_DROPSHIP_YZCM == 'dev_17') {
            $information_id=131;
        } elseif (ENV_DROPSHIP_YZCM == 'pro') {
            $information_id=134;
        } else {
            $information_id=134;
        }
        $data['help_center_url'] = $this->url->link('information/information', ['information_id' => $information_id]);

        $data['column_left'] = $this->load->controller('common/column_left');
        $data['column_notice'] = $this->load->controller('information/notice/column_notice');
        $data['footer'] = $this->load->controller('common/footer');
        $data['header'] = $this->load->controller('common/header');

        $this->response->setOutput($this->load->view('account/buyer_central', $data));
    }

    /**
     * 日历的待处理事件
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function getCalendarRemainder()
    {
        $country = session('country', 'USA');
        $fromZone = CountryHelper::getTimezoneByCode('USA');
        $toZone = CountryHelper::getTimezoneByCode($country);
        $data['data'] = app(CalendarReminderRepository::class)->getRemainder($fromZone, $toZone, true);
        return response()->json($data);
    }

    /**
     * 综合评分明细
     * @deprecated
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function comprehensiveInfo()
    {
        set_time_limit(0);
        //综合得分(综合评分)
        $this->load->model('customerpartner/seller_center/index');
        $data=$this->model_customerpartner_seller_center_index->comprehensiveSellerData(intval($this->customer->getId()),intval($this->customer->getCountryId()),2);
        return $this->response->json($data);
    }

    /**
     * @return array
     * @throws Exception
     */
    private function getBuyerInfo(): array
    {
        $this->load->model('tool/image');
        $info['pic'] = $this->model_tool_image->resize('head.png', 64, 64);
        $info['number'] = $this->customer->getUserNumber();
        $info['name'] = $this->customer->getFirstName() . $this->customer->getLastName();
        $info['is_collection_from_domicile'] = $this->customer->isCollectionFromDomicile();
        $info['type'] = $info['is_collection_from_domicile'] ? $this->language->get('tip_home_pickup_logo') : $this->language->get('tip_drop_shipping_logo');
        $info['account_balance'] = $this->currency->formatCurrencyPrice($this->customer->getLineOfCredit(), $this->session->data['currency']);
        $info['balance_link'] = $this->url->link('account/balance/buyer_balance');
        $info['recharge_link'] = $this->url->link('account/balance/recharge');
        $info['leasing_manager_pic'] = $this->model_tool_image->resize('manager.png', 64, 64);
        return $info;
    }

    /**
     * BD
     * @return array
     * @throws Exception
     */
    private function leasingManagerInfo():array
    {
        $this->load->model('tool/image');
        $info = $this->model_account_buyer_central->buyerLeasingManagerNamePhoneEmailByBuyerId($this->customer_id);         //print_r($info);die;
        $info['leasing_manage_pic'] = '';
        $width = 65;//width:height=1:1.4
        $height = 91;
        if (!empty($info) && isset($info['path']) && !empty($info['path'])) {
            $info['leasing_manage_pic'] = StorageCloud::root()->getUrl($info['path'], ['w' => $width, 'h' => $height]);
        } else {
            $info['leasing_manage_pic'] = $this->model_tool_image->resize('manager.png', $width, $height);
        }
        return $info;
    }

    /**
     * @return mixed
     * @throws Exception
     */
    private function getBuyerSaleOrderAndBidStatistics(): array
    {
        $this->load->model("account/customer_order");
        $this->load->model('account/customer_order_import');
        $param = array(
            'filter_cancel_not_applied_rma' => 1,
            'filter_orderStatus' => self::TB_SYS_CUSTOMER_SALES_ORDER_STATUS_INIT,
            'filter_tracking_number' => self::TB_SYS_CUSTOMER_SALES_ORDER_TRACKING_ALL,
            'customer_id' => $this->customer_id,
            'tracking_privilege' => $this->model_account_customer_order_import->getTrackingPrivilege($this->customer->getId(), $this->customer->isCollectionFromDomicile(), $this->customer->getCountryId()),
        );

        if ($this->customer->isCollectionFromDomicile()) {
            $param['filter_cancel_not_applied_rma'] = 1;
            $param['delivery_type'] = 1;

            $param['filter_orderStatus'] = self::TB_SYS_CUSTOMER_SALES_ORDER_STATUS_TO_BE_PAID;
            $toBePaidResult = $this->model_account_customer_order->queryOrderNum($param, true);
            $stats['to_be_paid'] = [
                'value' => $toBePaidResult['total'],
                'link' => $this->url->link('account/customer_order', 'from_buyer_central=true&filter_orderStatus=' . self::TB_SYS_CUSTOMER_SALES_ORDER_STATUS_TO_BE_PAID, true),
            ];
//            $param['filter_orderStatus'] = self::TB_SYS_CUSTOMER_SALES_ORDER_STATUS_LTL_TO_EMAIL;
//            $ltlToEmailResult = $this->model_account_customer_order->queryOrderNum($param, true);
//            $stats['ltl_to_email'] = [
//                'value' => $ltlToEmailResult['total'],
//                'link' => $this->url->link('account/customer_order', 'from_buyer_central=true&filter_orderStatus=' . self::TB_SYS_CUSTOMER_SALES_ORDER_STATUS_LTL_TO_EMAIL, true),
//            ];
            $param['filter_orderStatus'] = self::TB_SYS_CUSTOMER_SALES_ORDER_STATUS_BEING_PROCESSED;
            $param['filter_tracking_number'] = self::TB_SYS_CUSTOMER_SALES_ORDER_TRACKING_YES;
            $trackingResult = $this->model_account_customer_order->queryOrderNum($param, true);
            $stats['tracking'] = [
                'value' => $trackingResult['total'],
                'link' => $this->url->link('account/customer_order', 'from_buyer_central=true&filter_orderStatus=' . self::TB_SYS_CUSTOMER_SALES_ORDER_STATUS_BEING_PROCESSED . '&filter_tracking_number=' . self::TB_SYS_CUSTOMER_SALES_ORDER_TRACKING_YES, true),
            ];
        } else {
            $param['filter_orderStatus'] = self::TB_SYS_CUSTOMER_SALES_ORDER_STATUS_TO_BE_PAID;
            $stats['to_be_paid'] = [
                'value' => $this->sales_model->getSalesOrderTotal($this->customer_id, $param),
                'link' => $this->url->link('account/sales_order/sales_order_management', 'filter_orderStatus=' . self::TB_SYS_CUSTOMER_SALES_ORDER_STATUS_TO_BE_PAID, true),
            ];
//            $param['filter_orderStatus'] = self::TB_SYS_CUSTOMER_SALES_ORDER_STATUS_LTL_TO_EMAIL;
//            $stats['ltl_to_email'] = [
//                'value' => $this->sales_model->getSalesOrderTotal($this->customer_id, $param),
//                'link' => $this->url->link('account/sales_order/sales_order_management', 'filter_orderStatus=' . self::TB_SYS_CUSTOMER_SALES_ORDER_STATUS_LTL_TO_EMAIL, true),
//            ];
            $param['filter_orderStatus'] = self::TB_SYS_CUSTOMER_SALES_ORDER_STATUS_BEING_PROCESSED;
            $param['filter_tracking_number'] = self::TB_SYS_CUSTOMER_SALES_ORDER_TRACKING_YES;
            $stats['tracking'] = [
                'value' => $this->sales_model->getSalesOrderTotal($this->customer_id, $param),
                'link' => $this->url->link('account/sales_order/sales_order_management', 'filter_orderStatus=' . self::TB_SYS_CUSTOMER_SALES_ORDER_STATUS_BEING_PROCESSED . '&filter_tracking_number=' . self::TB_SYS_CUSTOMER_SALES_ORDER_TRACKING_YES, true),
            ];
        }

        //yzc\storage\modification\catalog\controller\common\header.php  和此文件中查询数字保持一致
        $rebateNum = 0;
        /** @var ModelAccountProductQuoteswkproductquotes $modelProduct */
        $modelProduct = load()->model('account/product_quotes/wk_product_quotes');
        $spotNum = $modelProduct->buyerQuoteAgreementsSomeStatusCount($this->customer->getId(), [0, 1]);
        // $this->load->model('account/product_quotes/margin');
        // $marginNum = $this->model_account_product_quotes_margin->getTabMarkCount();*/
        $marginTotal = (new MarginAgreementSearch(customer()->getId()))->getStatisticsNumber([],true);
        $marginNum =  $marginTotal['total_number'];
        $this->load->model('futures/agreement');
        $futuresNum = $this->model_futures_agreement->totalForBuyer($this->customer_id);
        $stats['pending_bid'] = [
            'value' => $spotNum + $rebateNum + $marginNum + $futuresNum,
            'link' => $this->url->link("account/product_quotes/wk_quote_my", '', true),
        ];

        $this->load->model('account/rma_management');
        $stats['not_reach_rma'] = [
            'value' => $this->model_account_rma_management->getRmaOrderInfoCount(['seller_status' => [self::VW_RMA_ORDER_INFO_STATUS_APPLIED, self::VW_RMA_ORDER_INFO_STATUS_PENDING]]),
            'link' => $this->url->link('account/rma_management', '', true),
        ];

        //保险
        $statisticsInfo = (new SafeguardClaimSearch(customer()->getId()))->getStatisticsNumber();
        $stats['safegurad_claim'] = [
            'value' => (int)($statisticsInfo['success_number'] + $statisticsInfo['fail_number'] + $statisticsInfo['backed_number']),
            'link' => $this->url->link('account/safeguard/bill#tab_claim_order', '', true),
        ];

        return $stats;
    }

    /**
     * @return mixed
     */
    private function getBuyerSalesRankings()
    {
        $nearlyOneMonthDate = date("Y-m-d 00:00:00", strtotime("-1 month +1 day"));
        $nearlyThreeMonthsDate = date("Y-m-d 00:00:00", strtotime("-3 month +1 day"));
        $nearlyOneYearDate = date("Y-m-d 00:00:00", strtotime("-1 year +1 day"));

        $ranks['nearly_one_month_ranks'] = $this->formatBuyerSalesRanks($this->model_account_buyer_central->buyerCompletedSaleRankingsByBuyerIdDateLimit($this->customer_id, $nearlyOneMonthDate, 7));
        $ranks['nearly_three_months_ranks'] = $this->formatBuyerSalesRanks($this->model_account_buyer_central->buyerCompletedSaleRankingsByBuyerIdDateLimit($this->customer_id, $nearlyThreeMonthsDate, 7));
        $ranks['nearly_one_year_ranks'] = $this->formatBuyerSalesRanks($this->model_account_buyer_central->buyerCompletedSaleRankingsByBuyerIdDateLimit($this->customer_id, $nearlyOneYearDate, 7));

        return $ranks;
    }

    /**
     * @param array $ranks
     * @return array
     */
    private function formatBuyerSalesRanks(array $ranks)
    {
        $productIds = array_column($ranks, 'product_id');
        if (empty($productIds)) {
            return [];
        }
        $productIdNameMap = $this->model_account_buyer_central->productNameByProductIds($productIds);
        $num = 1;
        foreach ($ranks as &$rank) {
            $rank['num'] = $num;
            $rank['product_name'] = $productIdNameMap[$rank['product_id']];
            $rank['link'] = $this->url->link('product/product', 'product_id=' . $rank['product_id'], true);
            $num++;
        }

        return $ranks;
    }

    /**
     * @return array
     */
    private function getPromotionActivities(): array
    {
        $promotionActivities = $this->model_account_buyer_central->promotionActivitiesExcludeBannerByCountryIdLimit(intval($this->customer->getCountryId()), 2);

        foreach ($promotionActivities as &$promotionActivity) {
            $promotionActivity['link'] = $this->url->link('marketing_campaign/activity/index', 'code=' . $promotionActivity['code'], true);
            $promotionActivity['apply_start_time'] = date('Y-m-d', strtotime(changeOutPutByZone($promotionActivity['apply_start_time'], $this->session, 'Y-m-d')));
            $promotionActivity['apply_end_time'] = date('Y-m-d', strtotime(changeOutPutByZone($promotionActivity['apply_end_time'], $this->session, 'Y-m-d')));
        }

        return $promotionActivities;
    }

    /**
     * @return array[]
     */
    private function getMenuNameLinks(): array
    {
        $this->load->model('account/mapping_management');
        $isShowMappingManagement = $this->model_account_mapping_management->isShowMappingManagement();

        $menus[] =  [
            'name' => $this->language->get('text_sales_order_management'),
            'link' => $this->url->to('account/customer_order'),
        ];
        $menus[] =  [
            'name' => $this->language->get('text_purchase_order_management'),
            'link' => $this->url->to('account/order'),
        ];
        $menus[] =  [
            'name' => $this->language->get('text_bid_list'),
            'link' => $this->url->to('account/product_quotes/wk_quote_my'),
        ];
        $menus[] =  [
            'name' => $this->language->get('text_billing_management'),
            'link' => $this->url->to('account/bill/sales_purchase_bill'),
        ];
        $menus[] =  [
            'name' => $this->language->get('text_rma_management'),
            'link' => $this->url->to('account/rma_management'),
        ];
        $menus[] =  [
            'name' => $this->language->get('text_inventory_management'),
            'link' => $this->url->link('account/stock/management'),
        ];
        $menus[] = [
            'name' => $this->language->get('text_safeguard_service_management'),
            'link' => url('account/safeguard/bill'),
        ];
        if ($isShowMappingManagement == 1) {
            $menus[] = [
                'name' => $this->language->get('text_external_platform_mapping'),
                'link' => $this->url->to('account/mapping_management'),
            ];
        }
        $menus[] =  [
            'name' => $this->language->get('text_sales_purchase_agreement'),
            'link' => $this->url->to('account/tripartite_agreement'),
        ];

        $menus[] =  [
            'name' => $this->language->get('text_seller_management'),
            'link' => $this->url->to('account/sellers'),
        ];
        $menus[] =  [
            'name' => $this->language->get('text_my_coupon'),
            'link' => $this->url->to('account/coupon/management_center'),
        ];
        $menus[] =  [
            'name' => $this->language->get('text_account_setting'),
            'link' => $this->url->to('account/setting'),
        ];
        $menus[] =  [
            'name' => $this->language->get('text_help_center'),
            'link' => $this->url->to('information/information'),
        ];

        return $menus;
    }

    /**
     * @return mixed
     * @throws Exception
     */
    private function getUnreadMessages()
    {
        $this->load->model('message/message');

        // 公告&通知 未读消息数
        $noticeRepo = app(NoticeRepository::class);
        $noticeCount = $noticeRepo->getNewNoticeCount($this->customer_id, $this->customer->getCountryId()); // 公告未读数
        $letterRepo = app(StationLetterRepository::class);
        $letterCount = $letterRepo->getNewStationLetterCount($this->customer_id); // 通知未读数
        $messageStatisticsRepo = app(MessageStatisticsRepositoryAlias::class);
        $unreadFromSeller = $messageStatisticsRepo->getCustomerInboxFromUserUnreadCount($this->customer_id); // 来自Seller消息的未读数
        $unreadFromGigaGenie = $messageStatisticsRepo->getCustomerInboxFromGigaGenieUnreadCount($this->customer_id); // 来自Seller消息的未读数
        return [
            'platform_secretary' => [
                'count' => $unreadFromGigaGenie,
                'link' => url()->to('account/message_center/platform_secretary'),
            ],
            'from_sellers' => [
                'count' => $unreadFromSeller,
                'link' => $this->url->link('account/message_center/seller'),
            ],
            'notice_and_letter' => [
                'count' => $noticeCount + $letterCount,
                'link' => $this->url->link('account/message_center/platform_notice'),
            ],
            'customer_service_reply' => [
                'count' => $this->model_message_message->unReadTicketCount($this->customer_id),
                'link' => $this->url->link('account/message_center/ticket'),
            ],
        ];
    }

    /**
     * @return array
     * @throws Exception
     */
    private function getNotices()
    {
        $search = new InboxSearch($this->customer_id, MsgReceiveSendType::PLATFORM_SECRETARY);
        $notice['messages'] = $search->get( [
            'filter_delete_status' => MsgDeleteStatus::NOT_DELETED,
            'page' => 1,
            'page_limit' => 4,
        ])['list'];

        $notice['notice_action_link'] = $this->url->to('account/message_center/platform_secretary');

        return $notice;
    }
}
