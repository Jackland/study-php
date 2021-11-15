<?php

use App\Catalog\Controllers\AuthBuyerController;
use App\Catalog\Search\Message\InboxSearch;
use App\Catalog\Search\Message\SentSearch;
use App\Enums\Message\MsgDeleteStatus;
use App\Enums\Message\MsgReceiveSendType;
use App\Repositories\Message\MessageRepository;
use App\Repositories\Message\StatisticsRepository;

/**
 * 平台小助手
 *
 * Class ControllerAccountMessageCenterPlatformSecretary
 */
class ControllerAccountMessageCenterPlatformSecretary extends AuthBuyerController
{
    private $customerId;
    private $countryId;

    public function __construct(Registry $registry)
    {
        parent::__construct($registry);
        $this->customerId = $this->customer->getId();
        $this->countryId = $this->customer->getCountryId();
        $this->load->language('account/message_center/platform_secretary');
    }

    public function index()
    {
        $statisticsRepo = app(StatisticsRepository::class);
        $data['unread_inbox_num'] = $statisticsRepo->getCustomerInboxFromGigaGenieUnreadCount($this->customerId); // 未读消息数
        $data['unread_list'] = app(MessageRepository::class)->getSlideShowUnreadList($this->customerId, $this->countryId); // 24小时内未读消息
        $data['message_column'] = $this->load->controller('account/message_center/column_left');
        $data['tab'] = request('tab', '');

        return $this->render('account/message_center/platform_secretary/index', $data, 'buyer');
    }

    /**
     * 发送列表
     *
     * @return string
     * @throws \Framework\Exception\InvalidConfigException
     */
    public function sent()
    {
        $filters = [
            'filter_delete_status' => MsgDeleteStatus::NOT_DELETED,
        ];

        $search = new SentSearch($this->customerId, MsgReceiveSendType::PLATFORM_SECRETARY);
        $data = $search->get(array_merge($this->request->query->all(), $filters));

        return $this->render('account/message_center/platform_secretary/sent', $data);
    }

    /**
     * 收件列表
     *
     * @return string
     * @throws \Framework\Exception\InvalidConfigException
     */
    public function inbox()
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

        $search = new InboxSearch($this->customerId, MsgReceiveSendType::PLATFORM_SECRETARY);
        $data = $search->get(array_merge($this->request->query->all(), $filters));

        if (!empty($filterRepliedStatus)) {
            $data['search']['filter_replied_status'] = array_flip($filterRepliedStatusMap)[$data['search']['filter_replied_status']];
        }

        return $this->render('account/message_center/platform_secretary/inbox', $data);
    }
}
