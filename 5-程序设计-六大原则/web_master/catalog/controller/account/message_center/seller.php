<?php

use App\Catalog\Controllers\AuthBuyerController;
use App\Catalog\Search\Message\InboxSearch;
use App\Catalog\Search\Message\SentReceiveSearch;
use App\Catalog\Search\Message\SentSearch;
use App\Enums\Message\MsgDeleteStatus;
use App\Repositories\Message\MessageRepository;
use App\Repositories\Message\StatisticsRepository as MessageStatisticsRepositoryAlias;
use Framework\Exception\InvalidConfigException;
use App\Catalog\Search\Message\SellerListSearch;

class ControllerAccountMessageCenterSeller extends AuthBuyerController
{
    private $customerId;
    private $countryId;

    public function __construct(Registry $registry)
    {
        parent::__construct($registry);
        $this->customerId = $this->customer->getId();
        $this->countryId = $this->customer->getCountryId();
        $this->load->language('account/message_center/message');
    }

    public function index()
    {
        $data['tab_type'] = $this->request->get('tab_type', 'inbox'); // 选择标签页
        $messageStatisticsRepo = app(MessageStatisticsRepositoryAlias::class);
        $data['unread_inbox_num'] = $messageStatisticsRepo->getCustomerInboxFromUserUnreadCount($this->customerId); // 来自Seller消息的未读数
        $data['unread_list'] = app(MessageRepository::class)->getSlideShowUnreadList($this->customerId, $this->countryId); // 24小时内未读消息
        $data['message_column'] = $this->load->controller('account/message_center/column_left');

        return $this->render('account/message_center/from_seller/index',$data, 'buyer');
    }

    /**
     * seller店铺列表
     */
    public function sellerList()
    {
        $search = new SellerListSearch($this->customerId, 0);
        $data = $search->get($this->request->get());

        return $this->render('account/message_center/from_seller/seller_list', $data);
    }

    /**
     * 获取所有Seller(只有名称和ID)
     */
    public function allSellerList()
    {
        $search = new SellerListSearch($this->customerId, 0);
        $data = $search->getAllSeller($this->request->get());

        return $this->jsonSuccess($data);
    }

    /**
     * 消息列表
     */
    public function list()
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
        $data['tab_type'] = $tabType;

        if (!empty($filterRepliedStatus)) {
            $data['search']['filter_replied_status'] = array_flip($filterRepliedStatusMap)[$data['search']['filter_replied_status']];
        }

        $data['unread_count'] = app(MessageStatisticsRepositoryAlias::class)->getCustomerInboxFromUserUnreadCount($this->customerId);
        return $this->render('account/message_center/from_seller/list', $data);
    }

    /**
     * 发送群发列表
     *
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

        return $this->render('account/message_center/from_seller/mass_receive', $data);
    }
}
