<?php

use App\Catalog\Controllers\AuthBuyerController;
use App\Catalog\Search\Message\InboxSearch;
use App\Catalog\Search\Message\TrashSearch;
use App\Enums\Customer\CustomerAccountingType;
use App\Enums\Message\MsgDeleteStatus;
use App\Enums\Message\MsgReceiveType;
use App\Enums\Message\MsgType;
use App\Repositories\Message\StatisticsRepository as MessageStatisticsRepositoryAlias;

class ControllerAccountMessageCenterSystem extends AuthBuyerController
{
    private $customerId;
    private $messageType;

    public function __construct(Registry $registry)
    {
        parent::__construct($registry);
        $this->customerId = $this->customer->getId();
        $this->messageType = $this->request->get('msg_type', 0);
        $this->load->language('account/message_center/message');
    }

    public function index()
    {
        $msgType = request('msg_type', '');
        $filerMsgType = request('filter_msg_type', '');
        $filters = [
            'filter_delete_status' => MsgDeleteStatus::NOT_DELETED,
        ];

        $filters = array_merge($this->request->query->all(), $filters);
        if (!empty($msgType) && empty($filerMsgType)) {
            $filters['filter_msg_type'] = $msgType;
        }
        // Product -> Type => other (product消息其他类型)
        if ($filerMsgType == '199') {
            $filters['filter_msg_type'] = MsgType::getBuyerProductOtherTypes();
        }

        // 特殊处理
        if (empty($filerMsgType)) {
            if ($msgType == MsgType::BID) { // bid
                $filters['filter_msg_type'] = MsgType::mainTypeMap()[MsgType::BID];
            } elseif ($msgType == MsgType::PRODUCT) { // product
                $filters['filter_msg_type'] = MsgType::getBuyerProductTypes();
            }
        }

        $search = new InboxSearch($this->customerId, MsgReceiveType::SYSTEM);
        $data = $search->get(array_merge($this->request->query->all(), $filters));
        $data['msg_type'] = $msgType;

        // Product -> Type => other (product消息其他类型)
        if ($filerMsgType == '199') {
            $data['search']['filter_msg_type'] = $filerMsgType;
        }
        $data['message_column'] = $this->load->controller('account/message_center/column_left');

        return $this->render('account/message_center/from_system/index', $data, 'buyer');
    }
}
