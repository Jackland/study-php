<?php

use App\Enums\Common\YesNoEnum;
use App\Enums\Customer\CustomerAccountingType;
use App\Enums\Message\MsgType;
use App\Logging\Logger;
use App\Models\ServiceAgreement\AgreementVersion;
use App\Helper\RouteHelper;
use App\Repositories\Common\CountryRepository;
use App\Repositories\Message\NoticeRepository;
use App\Repositories\Message\StationLetterRepository;
use App\Repositories\Message\StatisticsRepository as MessageStatisticsRepositoryAlias;
use App\Repositories\ServiceAgreement\ServiceAgreementRepository;
use App\Catalog\Search\Margin\MarginAgreementSearch;
use App\Repositories\Tripartite\AgreementRepository;
use Illuminate\Support\Str;
use App\Repositories\Customer\CustomerTipRepository;
use App\Catalog\Search\Safeguard\SafeguardClaimSearch;
use App\Repositories\Safeguard\SafeguardAutoBuyPlanRepository;

/**
 * Class ControllerCommonHeader
 * @property ModelAccountMappingManagement $model_account_mapping_management
 * @property ModelAccountProductQuotesMargin $model_account_product_quotes_margin
 * @property ModelAccountProductQuoteswkproductquotes $model_account_product_quotes_wk_product_quotes
 * @property ModelAccountRmaManagement $model_account_rma_management
 * @property ModelFuturesAgreement $model_futures_agreement
 * @property ModelSettingExtension $model_setting_extension
 */
class ControllerCommonHeader extends Controller
{
    /**
     * #27711 页面在未登录状态下，跳转到新域名对应的页面
     */
    private function redirectNewDomain()
    {
        if ($this->customer->isLogged()) {
            return;
        }

        $oldDomainName = configDB('old_domain_name', '');
        $newDomainName = configDB('new_domain_name', '');
        if (empty($oldDomainName) || empty($newDomainName)) {
            return;
        }

        if ($oldDomainName == $newDomainName) {
            return;
        }

        if (!Str::contains(request()->getHost(), $oldDomainName)) {
            return;
        }

        $url = request()->getScheme() . '://' . $newDomainName . '/' . ltrim(request()->server('REQUEST_URI'), '/');
        header("Location: {$url}");
        exit();
    }

    public function index($data = [])
    {
        $this->redirectNewDomain();

        // 102043 跳转服务协议
        $currentRoute = $this->request->get('route', '');
        if (
            $this->customer->isLogged()
            && app(ServiceAgreementRepository::class)->checkCustomerSignAgreement(AgreementVersion::AGREEMENT_ID_BY_CUSTOMER_LOGIN, customer()->getId())
            && $currentRoute != 'account/service_agreement'
            && $this->session->has('is_redirect_agreement')
        ) {
            if (!empty($currentRoute)) {
                $this->url->remember();
            }
            return $this->redirect('account/service_agreement')->send();
        }

        // 当前是否是店铺主页
        $data['is_seller_store'] = RouteHelper::isCurrentMatchGroup('storeHome');

        // Display
        $data['displayTop'] = $data['display_top'] ?? true;
        $data['display_search'] = $data['display_search'] ?? true;
        $data['display_account_info'] = $data['display_account_info'] ?? true;
        $data['display_menu'] = $data['is_seller_store'] ? false : ($data['display_menu'] ?? true);
        $data['display_register_header'] = $data['display_register_header'] ?? false;
        $data['display_forgot_password_header'] = $data['display_forgot_password_header'] ?? false;
        $data['display_common_ticket'] = $data['display_common_ticket'] ?? true;
        $data['display_shipment_time'] = $data['display_shipment_time'] ?? true;

        $this->load->model('account/rma_management');

        // Document
        $server = request()->server('HTTPS', false) ? configDB('config_ssl') : configDB('config_url');
        if (is_file(DIR_IMAGE . configDB('config_icon'))) {
            $this->document->addLink($server . 'image/' . configDB('config_icon'), 'icon');
        }
        // title 在 header.twig 中未使用，但是暂时保留，因为原来的逻辑中可能涉及到到内容页面使用 title 的情况
        $data['title'] = $this->document->getTitle();
        $data['base'] = $server;
        $data['description'] = $this->document->getDescription();
        $data['keywords'] = $this->document->getKeywords();
        $data['links'] = $this->document->getLinks();
        $data['styles'] = $this->document->getStyles();
        $data['scripts'] = $this->document->getScripts('header');
        $data['lang'] = $this->language->get('code');
        $data['direction'] = $this->language->get('direction');
        $data['name'] = $this->config->get('config_name');
        if (is_file(DIR_IMAGE . configDB('config_logo'))) {
            $data['logo'] = $server . 'image/' . configDB('config_logo');
        } else {
            $data['logo'] = '';
        }
        $data['app_version'] = APP_VERSION;

        // Base
        $data['home'] = $this->url->link('common/home');
        $data['logged'] = $this->customer->isLogged();
        $data['is_seller'] = (int)$this->customer->isPartner();
        $data['is_inner_seller'] = $data['is_seller'] && $this->customer->getAccountType() == CustomerAccountingType::INNER;
        // 临时控制Seller前台菜单 库存管理、Label打印 显示
        $data['special_menu_cont'] = $data['is_seller'] && $this->customer->getCountryId() == AMERICAN_COUNTRY_ID;

        // 修改页面样式
        $data['class'] = '';
        $route = $this->request->get('route');
        if ($route && !in_array($route, ['common/home', 'product/product'])) {
            $data['class'] = 'bg-grey';
        }

        // 未知的旧业务
        if ($this->config->get('module_marketplace_status')) {
            $data['marketplace_seller_mode'] = session('marketplace_seller_mode', 1);
        }

        // Language
        $this->load->language('common/header');

        // Wishlist
        if ($this->customer->isLogged()) {
            /** @var ModelAccountWishlist $modelAccountWishlist */
            $modelAccountWishlist = load()->model('account/wishlist');
            $data['text_wishlist_num'] = $modelAccountWishlist->getTotalWishlist();
        } else {
            $data['text_wishlist_num'] = count(session('wishlist', []));
        }
        $data['text_wishlist'] = $this->language->get('text_wishlist');

        // Trusteeship
        $data['is_trusteeship'] = YesNoEnum::NO;
        if ($this->customer->isLogged()) {
            if (
                $this->customer->getTrusteeship()
                && $this->customer->getCountryId() == AMERICAN_COUNTRY_ID
                && $this->orm->table('tb_sys_store_to_buyer')->where('buyer_id', $this->customer->getId())->exists()
            ) {
                $data['is_trusteeship'] = YesNoEnum::YES;
            }
        }

        // 登录信息
        if ($this->customer->isLogged()) {
            if ($this->customer->isPartner()) {
                // seller
                $data['userName'] = $this->customer->getModel()->seller->screenname;
                $data['userNumber'] = "({$this->customer->getModel()->user_number}-Seller)";
            } else {
                // buyer
                $data['userName'] = $this->customer->getModel()->nickname;
                $data['userNumber'] = "({$this->customer->getModel()->user_number}-Buyer)";
            }
        }

        // CustomerOrder
        $data['show_customer_order'] = '';
        if ($this->customer->isLogged() && !$this->customer->isPartner()) {
            // 只能Buyer账号可见
            if ($this->customer->isCollectionFromDomicile()) {
                // 101592 一件代发 taixing
                $data['show_customer_order'] = url('account/customer_order');
            } else {
                $data['show_customer_order'] = url('account/sales_order/sales_order_management');
            }
        }

        // Country
        $data['country'] = $this->load->controller('common/country');
        // Search
        $data['search'] = $this->load->controller('common/search');

        // Cart
        $data['cart'] = $this->load->controller('common/cart');

        // Menu
        if ($data['display_menu']) {
            $data['menu'] = $this->load->controller('common/menu');
        }

        // 钱包
        if ($this->customer->isLogged() && !$this->customer->isPartner()) {
            $data['account_balance'] = $this->load->controller('account/balance/buyer_balance/balanceMenu');
        }

        // 消息中心
        $data['message_center_count'] = $this->getMessageCenterCount();

        // ShipmentTime
        if ($data['display_shipment_time'] && $this->customer->isLogged() && $moduleShipmentTimeStatus = $this->config->get('module_shipment_time_status')) {
            $country = app(CountryRepository::class)->getByCode(session('country'));
            /** @var ModelExtensionModuleShipmentTime $modelShipmentTime */
            $modelShipmentTime = load()->model('extension/module/shipment_time');
            $shipmentTimePage = $modelShipmentTime->getShipmentTime($country->country_id);
            $data['module_shipment_time_status'] = $moduleShipmentTimeStatus;
            $data['shipmentTimePage'] = $shipmentTimePage;
        }

        // 头部导航栏中，未读的Ticket数量
        if ($this->customer->isLogged()) {
            $data['ticketBuyer'] = $this->load->controller('common/ticket');
        }

        // External Platform Mapping
        if ($this->customer->isLogged()) {
            /** @var ModelAccountMappingManagement $mappingManagement */
            $mappingManagement = load()->model('account/mapping_management');
            $data['isShowMappingManagement'] = $mappingManagement->isShowMappingManagement();
        }

        // 品牌是否展示
        $data['brand_show'] = $this->config->get('brand_show');

        // Bid List Count
        if ($this->customer->isLogged() && !$this->customer->isPartner()) {
            $data['bid_mark_count'] = $this->cache->getOrSet([__CLASS__, __FUNCTION__, $this->customer->getId(), 'v1'], function () {
                //N-624现货保证金三期
                //Tab标签的待处理的Spot数量
                /** @var ModelAccountProductQuoteswkproductquotes $modelProduct */
                $modelProduct = load()->model('account/product_quotes/wk_product_quotes');
                $countSpot = $modelProduct->buyerQuoteAgreementsSomeStatusCount($this->customer->getId(), [0, 1]);
                //Tab标签的待处理的Rebate数量
                $countRebate = 0;
                //Tab标签的待处理的现货保证金协议数量
                /** @var ModelAccountProductQuotesMargin $modelMargin */
                //$modelMargin = load()->model('account/product_quotes/margin');
                //$countMargin = $modelMargin->getTabMarkCount();
                $marginTotal = (new MarginAgreementSearch(customer()->getId()))->getStatisticsNumber([],true);
                $countMargin =  $marginTotal['total_number'];
                //Tab标签待处理的期货协议数量
                /** @var ModelFuturesAgreement $modelAgreement */
                $modelAgreement = load()->model('futures/agreement');
                $countFuture = $modelAgreement->totalForBuyer($this->customer->getId());

                return $countSpot + $countRebate + $countMargin + $countFuture;
            }, 10);
        }

        // 已提交未处理的rma数量
        if ($this->customer->isLogged()) {
            $data['unresolved_rma_count'] = $this->model_account_rma_management->getRmaOrderInfoCount(['seller_status' => [1, 3]]);
        }

        //保障服务管理(Buyer Protection Management)
        if (!app(CustomerTipRepository::class)->checkCustomerTipExistsByTypeKey($this->customer->getId() ?? 0, 'safeguard_new')) {
            $data['safeguardMarkTip'] = 'New';
        } else {
            $search = new SafeguardClaimSearch(customer()->getId());
            $claim = $search->getStatisticsNumber();
            $claimCount = (int)$claim['success_number'] + (int)$claim['fail_number'] + (int)$claim['backed_number'];
            $data['safeguardMarkTip'] = $claimCount > 99 ? '99+' : $claimCount;
        }
        //自动购买即将过期
        if (app(SafeguardAutoBuyPlanRepository::class)->isAboutToExpireByDays((int)$this->customer->getId() ?? 0) && boolval(Customer()->getCustomerExt(1))) {
            $data['safeguardAutoToExpire'] = true;
        }
        // 是否在 seller store页面
        $data['is_seller_store'] = RouteHelper::isCurrentMatchGroup('storeHome');

        //获取采销协议状态数量
        $data['seller_pending_nums'] = app(AgreementRepository::class)->getSellerRequestNum((int)$this->customer->getId());

        return $this->load->view('common/header', $data);
    }

    /**
     * 获取未读消息数
     *
     * @return int
     */
    private function getMessageCenterCount()
    {
        if (!$this->customer->isLogged()) {
            return 0;
        }

        // 公告&通知 未读消息数
        $noticeRepo = app(NoticeRepository::class);
        $noticeCount = $noticeRepo->getNewNoticeCount($this->customer->getId(), $this->customer->getCountryId(), $this->customer->isPartner() ? 1 : 0); // 公告未读数
        $letterRepo = app(StationLetterRepository::class);
        $letterCount = $letterRepo->getNewStationLetterCount($this->customer->getId()); // 通知未读数

        $messageStatisticsRepo = app(MessageStatisticsRepositoryAlias::class);
        $unreadMsg = $messageStatisticsRepo->getCustomerInboxUnreadCount($this->customer->getId()); // 来自Seller消息的未读数

        try {
            /** @var ModelMessageMessage $message */
            $message = load()->model('message/message');
            $ticketCount = $message->unReadTicketCount($this->customer->getId()); // 未读Ticket数
        } catch (Exception $e) {
            Logger::error('查询Ticket未读数发生异常:' . $e->getMessage());

            $ticketCount = 0;
        }

        return $noticeCount + $letterCount + $unreadMsg + $ticketCount;
    }
}
