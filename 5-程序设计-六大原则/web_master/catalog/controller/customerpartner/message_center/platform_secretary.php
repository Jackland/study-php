<?php

use App\Catalog\Controllers\AuthSellerController;
use App\Catalog\Search\Message\InboxSearch;
use App\Catalog\Search\Message\SentSearch;
use App\Enums\Message\MsgDeleteStatus;
use App\Enums\Message\MsgReceiveSendType;
use App\Repositories\Message\StatisticsRepository as MessageStatisticsRepository;
use Framework\Exception\InvalidConfigException;

class ControllerCustomerpartnerMessageCenterPlatformSecretary extends AuthSellerController
{
    /**
     * 平台小助手页面
     * @return string
     */
    public function index()
    {
        $data['unread_count'] = app(MessageStatisticsRepository::class)->getCustomerInboxFromGigaGenieUnreadCount(customer()->getId());
        $data['tab'] = request('tab', '');

        return $this->render('customerpartner/message_center/platform_secretary/index', $data, 'seller');
    }

    /**
     * 平台小助手发送
     * @return string
     * @throws InvalidConfigException
     */
    public function sent()
    {
        $filters = [
            'filter_delete_status' => MsgDeleteStatus::NOT_DELETED,
        ];

        $search = new SentSearch(customer()->getId(), MsgReceiveSendType::PLATFORM_SECRETARY);
        $data = $search->get(array_merge($this->request->query->all(), $filters));

        return $this->render('customerpartner/message_center/platform_secretary/sent', $data);
    }

    /**
     * 平台小助手接受
     * @return string
     * @throws InvalidConfigException
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

        $search = new InboxSearch(customer()->getId(), MsgReceiveSendType::PLATFORM_SECRETARY);
        $data = $search->get(array_merge($this->request->query->all(), $filters));

        if (!empty($filterRepliedStatus)) {
            $data['search']['filter_replied_status'] = array_flip($filterRepliedStatusMap)[$data['search']['filter_replied_status']];
        }

        return $this->render('customerpartner/message_center/platform_secretary/inbox', $data);
    }
}
