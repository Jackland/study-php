<?php

use App\Catalog\Controllers\AuthBuyerController;
use App\Enums\Message\MsgDeleteStatus;
use App\Enums\Message\MsgReceiveType;
use App\Models\Message\Msg;
use App\Repositories\Message\NoticeRepository;
use App\Repositories\Message\StationLetterRepository;
use App\Repositories\Message\StatisticsRepository as MessageStatisticsRepositoryAlias;
use App\Enums\Message\MsgType;

/**
 * @property ModelMessageMessage $model_message_message
 */
class ControllerAccountMessageCenterColumnLeft extends AuthBuyerController
{
    const MESSAGE_MENU_ID_PLATFORM_NOTICE = 'message-platform-notice';
    const MESSAGE_MENU_ID_SELLER = 'message-seller';
    const MESSAGE_MENU_ID_PRODUCT_STOCK = 'message-product_stock';
    const MESSAGE_MENU_ID_PRODUCT = 'message-product';
    const MESSAGE_MENU_ID_BID = 'message-bid';
    const MESSAGE_MENU_ID_ORDER = 'message-order';
    const MESSAGE_MENU_ID_RMA = 'message-rma';
    const MESSAGE_MENU_ID_OTHER = 'message-other';
    const MESSAGE_MENU_ID_TRASH = 'message-trash';
    const MESSAGE_MENU_ID_TICKET_LIST = 'account-ticket-list';
    const MESSAGE_MENU_ID_SETTING_ALERT = 'message-setting-alert';
    const MESSAGE_MENU_ID_SETTING_MY_COMPLAIN = 'message-setting-my-complain';
    const MESSAGE_MENU_ID_SETTING_SETTING_LANG = 'message-setting-lang';

    const MESSAGE_MENU_ID_ONE = 'message-menu-one';
    const MESSAGE_MENU_ID_TWO = 'message-menu-two';
    const MESSAGE_MENU_ID_THREE = 'message-menu-three';
    const MESSAGE_MENU_ID_FOUR = 'message-menu-four';

    protected $customerId;
    protected $countryId;

    public function __construct(Registry $registry)
    {
        parent::__construct($registry);

        $this->customerId = $this->customer->getId();
        $this->countryId = $this->customer->getCountryId();
        $this->load->language('account/message_center/column_left');
    }

    public function index()
    {
        // 公告&通知 未读消息数
        $noticeRepo = app(NoticeRepository::class);
        $noticeCount = $noticeRepo->getNewNoticeCount($this->customerId, $this->countryId); // 公告未读数
        $letterRepo = app(StationLetterRepository::class);
        $letterCount = $letterRepo->getNewStationLetterCount($this->customerId); // 通知未读数

        $messageStatisticsRepo = app(MessageStatisticsRepositoryAlias::class);
        $unreadFromSeller = $messageStatisticsRepo->getCustomerInboxFromUserUnreadCount($this->customerId); // 来自Seller消息的未读数
        $unreadFromAlert = $messageStatisticsRepo->getCustomerInboxFromSomeTypeSystemUnreadCount($this->customerId, MsgType::PRODUCT_INVENTORY); // 库存预警未读数
        $typesCount = $messageStatisticsRepo->getCustomerInboxFromSystemUnreadMainTypesCount($this->customerId); // 系统消息未读数
        $unreadFromGigaGenie = $messageStatisticsRepo->getCustomerInboxFromGigaGenieUnreadCount($this->customerId); // 平台小秘书未读数

        /** @var ModelMessageMessage $message */
        $message = load()->model('message/message');
        $ticketCount = $message->unReadTicketCount($this->customer->getId()); // 未读Ticket数

        // My Message
        $menu_one = [
            [
                'id' => self::MESSAGE_MENU_ID_PLATFORM_NOTICE,
                'name' => $this->language->get('text_li_notice'),
                'href' => $this->url->link('account/message_center/platform_notice'),
                'children' => [],
                'unread'=> $noticeCount + $letterCount
            ], // 公告&通知
            [
                'id' => self::MESSAGE_MENU_ID_SELLER,
                'name' => $this->language->get('text_li_from_seller'),
                'href' => $this->url->link('account/message_center/seller'),
                'children' => [],
                'unread'=> $unreadFromSeller
            ], // From Seller
            [
                'id' => self::MESSAGE_MENU_ID_PRODUCT_STOCK,
                'name' => $this->language->get('text_product_stock'),
                'href' => $this->url->link('account/message_center/system', ['msg_type' => 107]),
                'children' => [],
                'unread'=> $unreadFromAlert
            ], // 库存报警
            [
                'id' => self::MESSAGE_MENU_ID_PRODUCT,
                'name' => $this->language->get('text_product'),
                'href' => $this->url->link('account/message_center/system', ['msg_type' => 100]),
                'children' => [],
                'unread'=> (isset($typesCount[100]) && $typesCount[100] - $unreadFromAlert > 0) ? ($typesCount[100] - $unreadFromAlert) : 0
            ], // Product
            [
                'id' => self::MESSAGE_MENU_ID_BID,
                'name' => $this->language->get('text_bid'),
                'href' => $this->url->link('account/message_center/system', ['msg_type' => 300]),
                'children' => [],
                'unread'=> isset($typesCount[300]) ? $typesCount[300] : 0
            ], // BID
            [
                'id' => self::MESSAGE_MENU_ID_ORDER,
                'name' => $this->language->get('text_order'),
                'href' => $this->url->link('account/message_center/system', ['msg_type' => 400]),
                'children' => [],
                'unread'=> isset($typesCount[400]) ? $typesCount[400] : 0
            ], // Order
            [
                'id' => self::MESSAGE_MENU_ID_RMA,
                'name' => $this->language->get('text_rma'),
                'href' => $this->url->link('account/message_center/system', ['msg_type' => 200]),
                'children' => [],
                'unread'=> isset($typesCount[200]) ? $typesCount[200] : 0
            ], // Rma
            [
                'id' => self::MESSAGE_MENU_ID_OTHER,
                'name' => $this->language->get('text_other'),
                'href' => $this->url->link('account/message_center/system', ['msg_type' => 500]),
                'children' => [],
                'unread'=> isset($typesCount[500]) ? $typesCount[500] : 0
            ], // Other
            [
                'id' => self::MESSAGE_MENU_ID_TRASH,
                'name' => $this->language->get('text_trash'),
                'href' => $this->url->link('account/message_center/message/trash'),
                'children' => [],
                'unread'=> 0
            ], // Trash
        ];
        $data['menus'][] = array(
            'id' => self::MESSAGE_MENU_ID_ONE,
            'icon' => '',
            'name' => $this->language->get('text_ul_main'),
            'href' => '',
            'unread'=> 100,
            'children' => $menu_one
        );

        // Customer Service
        $menu_two = [
            [
                'id' => self::MESSAGE_MENU_ID_TICKET_LIST,
                'name' => $this->language->get('text_li_ticket'),
                'href' => $this->url->link('account/message_center/ticket'),
                'children' => [],
                'unread' => $ticketCount
            ]
        ];
        $data['menus'][] = array(
            'id' => self::MESSAGE_MENU_ID_TWO,
            'icon' => '',
            'name' => $this->language->get('text_ul_ticket'),
            'href' => '',
            'unread'=> 0,
            'children' => $menu_two
        );

        // 我的通讯录
        $data['menus'][] = array(
            'id' => self::MESSAGE_MENU_ID_THREE,
            'icon' => '',
            'name' => $this->language->get('text_my_address_book'),
            'href' => $this->url->link('account/message_center/address_book'),
            'unread'=> 0,
            'children' => []
        );

        // 消息设置
        $menu_four = [
            [
                'id' => self::MESSAGE_MENU_ID_SETTING_ALERT,
                'name' => $this->language->get('text_alerts_setting'),
                'href' => $this->url->link('account/message_center/setting'),
                'children' => [],
                'unread'=> 0
            ], // Alerts Setting
//            [
//                'id' => 'message-setting-useful-expr',
//                'name' => $this->language->get('text_useful_expr'),
//                'href' => $this->url->link('account/message_center/suggest'),
//                'children' => [],
//                'unread'=> 0
//            ], // 常用语建议
            [
                'id' => self::MESSAGE_MENU_ID_SETTING_MY_COMPLAIN,
                'name' => $this->language->get('text_my_complain'),
                'href' => $this->url->link('account/message_center/complain'),
                'children' => [],
                'unread'=> 0
            ], // 我的投诉
            [
                'id' => self::MESSAGE_MENU_ID_SETTING_SETTING_LANG,
                'name' => $this->language->get('text_lang'),
                'href' => $this->url->link('account/message_center/language'),
                'children' => [],
                'unread'=> 0
            ], // 我的投诉
        ];
        $data['menus'][] = array(
            'id' => self::MESSAGE_MENU_ID_FOUR,
            'icon' => '',
            'name' => $this->language->get('text_message_setting'),
            'href' => '',
            'unread'=> 0,
            'children' => $menu_four
        );
        $data['under_giga_genie'] = $unreadFromGigaGenie;
        $data['checked_btn_id'] = $this->dealMenuSelect($data);

        return $this->render('account/message_center/common/column_left', $data);
    }

    /**
     * 菜单选择处理
     *
     * @param array $data 菜单数组
     * @return string
     */
    private function dealMenuSelect(array $data)
    {
        // 菜单选择
        $route = $this->request->get('route');
        if ($route == 'account/message_center/system') { // 系统消息区分菜单特殊处理
            $route .= '&msg_type=' . $this->request->get('msg_type');
        }
        // 详情去对应前一个页面路由(除通知)
        if ($route == 'account/message_center/message/detail') {
            $route = $this->request->server('HTTP_REFERER');
            // 消息详情中判断
            if ($msgId = request('msg_id', '')) {
                /** @var Msg $message */
                $message = Msg::queryRead()->find($msgId);
                if ($message->delete_status == MsgDeleteStatus::TRASH) {
                    return self::MESSAGE_MENU_ID_TRASH;
                }
                if ($message->sender_id == -1 || ($message->sender_id > 0 && $message->receive_type == MsgReceiveType::PLATFORM_SECRETARY)) {
                    return '';
                }
                if ($message->sender_id > 0 && $message->receive_type == MsgReceiveType::USER) {
                    return self::MESSAGE_MENU_ID_SELLER;
                }
                if ($message->msg_type == 107) {
                    return self::MESSAGE_MENU_ID_PRODUCT_STOCK;
                }
                $route = 'account/message_center/system&msg_type=' . floor($message->msg_type / 10) * 10;
            }
        }
        // 通知详情特殊处理 -- 通知详情有多个不统一入口
        if ($route == 'account/message_center/platform_notice/view') {
            $route = 'account/message_center/platform_notice';
        }
        foreach ($data['menus'] as $menus) {
            if ($menus['children']) {
                foreach ($menus['children'] as $item) {
                    if (stristr($route,  substr($item['href'], stripos($item['href'], '=') + 1)) !== false) {
                        return $item['id'];
                    }
                }
            } else {
                if (stristr($route, substr($menus['href'], stripos($menus['href'], '=') + 1)) !== false) {
                    return $menus['id'];
                }
            }
        }

        return '';
    }
}
