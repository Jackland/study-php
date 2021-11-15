<?php

use App\Catalog\Controllers\AuthSellerController;
use App\Catalog\Search\Message\InboxSearch;
use App\Catalog\Search\Message\NoticeSearch;
use App\Catalog\Search\Message\SentReceiveSearch;
use App\Catalog\Search\Message\SentSearch;
use App\Catalog\Search\Message\TrashSearch;
use App\Enums\Customer\CustomerAccountingType;
use App\Enums\Message\MsgDeleteStatus;
use App\Enums\Message\MsgReceiveType;
use App\Enums\Message\MsgType;
use App\Models\Setting\Dictionary;
use App\Repositories\Message\NoticeRepository;
use App\Repositories\Message\StationLetterRepository;
use App\Repositories\Message\StatisticsRepository as MessageStatisticsRepositoryAlias;
use App\Repositories\Setting\MessageSettingRepository;
use Framework\Exception\InvalidConfigException;

class ControllerCustomerpartnerMessageCenterMyMessage extends AuthSellerController
{
    private $customerId;

    public function __construct(Registry $registry)
    {
        parent::__construct($registry);
        $this->customerId = customer()->getId();
    }

    /**
     * @return string
     * @throws InvalidConfigException
     */
    public function buyers()
    {
        $filters = [
            'filter_delete_status' => MsgDeleteStatus::NOT_DELETED,
        ];

        // filter_replied_status 的特殊处理 5=>0, 6=>1, 7=>2
        $filterRepliedStatusMap = [
            '5' => '0',
            '6' => '1',
            '7' => '2',
        ];
        $filterRepliedStatus = request('filter_replied_status', '');
        if (!empty($filterRepliedStatus)) {
            $filters['filter_replied_status'] = $filterRepliedStatusMap[$filterRepliedStatus];
        }

        $tabType = request('tab_type', 'inbox');
        if ($tabType == 'sent') {
            $search = new SentSearch($this->customerId);
        } else {
            $search = new InboxSearch($this->customerId);
        }

        $data = $search->get(array_merge($this->request->query->all(), $filters));
        $data['message_column'] = $this->column('message-seller');
        $data['tab_type'] = $tabType;

        if (!empty($filterRepliedStatus)) {
            $data['search']['filter_replied_status'] = array_flip($filterRepliedStatusMap)[$data['search']['filter_replied_status']];
        }

        $data['unread_count'] = app(MessageStatisticsRepositoryAlias::class)->getCustomerInboxFromUserUnreadCount($this->customerId);

        return $this->render('customerpartner/message_center/my_message/buyers', $data, 'seller');
    }

    /**
     * @return string
     * @throws InvalidConfigException
     */
    public function system()
    {
        $msgType = request('msg_type', '');
        $filerMsgType = request('filter_msg_type', '');
        $filters = [
            'filter_delete_status' => MsgDeleteStatus::NOT_DELETED,
        ];

        if (!empty($msgType) && empty($filerMsgType)) {
            $filters['filter_msg_type'] = $msgType;
        }
        // 300 bid 特殊处理
        if ($msgType == MsgType::BID && empty($filerMsgType)) {
            $filters['filter_msg_type'] = MsgType::mainTypeMap()[MsgType::BID];
        }

        $search = new InboxSearch($this->customerId, MsgReceiveType::SYSTEM);
        $data = $search->get(array_merge($this->request->query->all(), $filters));
        $data['message_column'] = $this->column('message-system');
        $data['msg_type'] = $msgType;

        // 300 bid 特殊处理
        if ($msgType == MsgType::BID && empty($filerMsgType)) {
            $data['search']['filter_msg_type'] = '';
        }
        if (!empty($msgType) && empty($filerMsgType)) {
            $data['search']['filter_msg_type'] = '';
        }

        $typesCount = app(MessageStatisticsRepositoryAlias::class)->getCustomerInboxFromSystemUnreadMainTypesCount($this->customerId);
        $typesCount['000'] = array_sum(array_values($typesCount));
        $data['unread_types_count'] = $typesCount;

        // 外部seller 展示 Incoming Shipment
        $data['is_usa_outer_account'] = customer()->isUSA() && customer()->getAccountType() == CustomerAccountingType::OUTER;

        return $this->render('customerpartner/message_center/my_message/system', $data, 'seller');
    }

    /**
     * @return string
     * @throws InvalidConfigException
     */
    public function trash()
    {
        $search = new TrashSearch();
        $data = $search->get($this->request->query->all());
        $data['message_column'] = $this->column('message-trash');

        return $this->render('customerpartner/message_center/my_message/trash', $data, 'seller');
    }

    /**
     * @return string
     * @throws Exception
     */
    public function notice()
    {
        $search = new NoticeSearch();
        $filters['filter_type_id'] = request('filter_type_id') ?? request('tab', 0);
        $filters['filter_delete_status'] = 0;
        $data = $search->get(array_merge($this->request->query->all(), $filters));

        //获取顶部统计数据
        $data['unread_notice_count'] = app(NoticeRepository::class)->getNewNoticeCount($this->customerId, customer()->getCountryId(), 1);;
        $data['unread_station_letter_count'] = app(StationLetterRepository::class)->getNewStationLetterCount($this->customerId);

        //公告类型
        $data['platform_type'] = Dictionary::queryRead()->where('DicCategory', 'PLAT_NOTICE_TYPE')->pluck('DicValue', 'DicKey')->toArray();

        //通知类型
        $data['letter_type'] = Dictionary::queryRead()->where('DicCategory', 'STATION_LETTER_TYPE')->pluck('DicValue', 'DicKey')->toArray();

        $data['message_column'] = $this->column('message-platform-notice');
        $data['tab'] = request('tab', 0);

        return $this->render('customerpartner/message_center/my_message/notice', $data, 'seller');
    }

    /**
     * @return string
     */
    public function setting()
    {
        $data['message_column'] = $this->column('message-setting');
        $data['messageSetting'] = app(MessageSettingRepository::class)->getByCustomerId($this->customer->getId(), [
            'setting' => 'setting_formatted',
            'email_setting' => 'email_setting_formatted',
            'other_email' => 'other_email_formatted',
        ]);
        $data['email'] = customer()->getEmail();

        return $this->render('customerpartner/message_center/my_message/setting', $data, 'seller');
    }

    /**
     * 发送群发列表
     * @return string
     * @throws InvalidConfigException
     */
    public function massReceiveList()
    {
        $msgId = request('msg_id', '');
        if (empty($msgId)) {
            return $this->redirect(url('error/not_found'));
        }

        $search = new SentReceiveSearch($this->customerId, $msgId);
        $data = $search->get($this->request->query->all());

        return $this->render('customerpartner/message_center/my_message/mass_receive', $data);
    }

    /**
     * 公用头部
     * @param string $menuId
     * @return string
     */
    private function column(string $menuId = '')
    {
        // 公告&通知 未读消息数
        $noticeCount = app(NoticeRepository::class)->getNewNoticeCount($this->customerId, customer()->getCountryId(), 1);
        $letterCount = app(StationLetterRepository::class)->getNewStationLetterCount($this->customerId);

        $messageMains = [
            [
                'id' => 'message-seller',
                'name' => 'From Buyers',
                'href' => url('customerpartner/message_center/my_message/buyers'),
                'unread' => app(MessageStatisticsRepositoryAlias::class)->getCustomerInboxFromUserUnreadCount($this->customerId),
            ],
            [
                'id' => 'message-system',
                'name' => 'System Alerts',
                'href' => url('customerpartner/message_center/my_message/system'),
                'unread' => app(MessageStatisticsRepositoryAlias::class)->getCustomerInboxFromSystemUnreadCount($this->customerId),
            ],
            [
                'id' => 'message-platform-notice',
                'name' => 'Notifications and Announcements',
                'href' => url('customerpartner/message_center/my_message/notice'),
                'unread' => $noticeCount + $letterCount,
            ],
            [
                'id' => 'message-trash',
                'name' => 'Trash',
                'href' => url('customerpartner/message_center/my_message/trash'),
                'unread' => 0
            ],
            [
                'id' => 'message-setting',
                'name' => 'Message Alerts',
                'href' => url('customerpartner/message_center/my_message/setting'),
                'unread' => 0
            ],
        ];

        return $this->render('customerpartner/message_center/my_message/column', ['messageMenus' => $messageMains, 'checked_btn_id' => $menuId]);
    }
}
