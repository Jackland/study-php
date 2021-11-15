<?php

namespace App\Catalog\Search\Message;

use App\Enums\Message\MsgReceiveSendType;
use App\Enums\Message\MsgType;
use App\Models\Customer\Customer;
use App\Models\CustomerPartner\CustomerPartnerToCustomer;
use App\Models\Message\Msg;
use App\Models\Message\MsgContent;
use App\Models\Message\MsgReceive;
use App\Services\Message\MessageService;
use App\Widgets\VATToolTipWidget;
use Framework\DataProvider\Paginator;
use Framework\DataProvider\QueryDataProvider;
use Framework\DataProvider\SearchModelTrait;
use Framework\DataProvider\Sort;
use Illuminate\Database\Eloquent\Collection;

class InboxSearch
{
    use SearchModelTrait;

    private $receiverId;
    private $sendType;
    private $isSeller;

    private $searchAttributes = [
        'filter_subject' => '',
        'filter_post_time_from' => '',
        'filter_post_time_to' => '',
        'filter_read_status' => '',
        'filter_mark_status' => '',
        'filter_sender' => '',
        'filter_msg_type' => '',
        'filter_replied_status' => '',
        'filter_delete_status' => '',
    ];

    /**
     * MessageSearch constructor.
     * @param int $receiverId
     * @param int $sendType
     */
    public function __construct(int $receiverId, int $sendType = MsgReceiveSendType::USER)
    {
        $this->receiverId = $receiverId;
        $this->sendType = $sendType;
        $this->isSeller = customer()->isPartner();
    }

    /**
     * @param $params
     * @return array
     * @throws \Framework\Exception\InvalidConfigException
     */
    public function get($params)
    {
        $this->loadAttributes($params);

        $query = $this->buildQuery();

        $dataProvider = new QueryDataProvider($query);

        $dataProvider->setSort(new Sort([
            'defaultOrder' => ['id' => SORT_DESC],
            'rules' => [
                'id' => 'r.id',
            ],
        ]));

        $dataProvider->setPaginator(new Paginator([
            'defaultPageSize' => 10,
        ]));

        $data['total'] = $dataProvider->getTotalCount();
        $data['list'] = $this->formatList($dataProvider->getList());
        $data['paginator'] = $dataProvider->getPaginator();
        $data['sort'] = $dataProvider->getSort();
        $data['search'] = $this->getSearchData();

        return $data;
    }

    /**
     * @param Collection $list
     * @return array
     */
    private function formatList(Collection $list): array
    {
        $senderIds = $list->where('sender_id', '>', 0)->pluck('sender_id')->toArray();
        $customerIdMap = Customer::queryRead()->alias('c')->with('buyer')->leftJoinRelations('seller as s')
            ->whereIn('c.customer_id', $senderIds)
            ->select(['c.nickname', 'c.firstname', 'c.lastname', 'c.customer_group_id', 'c.user_number', 'c.customer_id', 's.screenname', 'c.vat', 'c.country_id'])
            ->get()
            ->keyBy('customer_id');

        $msgContentIdMap = [];
        if ($this->sendType == MsgReceiveSendType::SYSTEM) {
            $msgIds = $list->pluck('msg_id')->toArray();
            $msgContentIdMap = MsgContent::queryRead()->whereIn('msg_id', $msgIds)->pluck('content', 'msg_id')->toArray();
        }

        $data = [];
        foreach ($list as $msg) {
            $msgData['id'] = $msg->id;
            $msgData['msg_id'] = $msg->msg_id;
            $msgData['subject'] = app(MessageService::class)->replaceMsgTitle($msg->title, $msg->msg_type,  $msgContentIdMap[$msg->msg_id] ?? '');
            $msgData['is_read'] = $msg->is_read;
            $msgData['replied_status'] = $msg->replied_status;
            $msgData['replied_status_name'] = $msg->replied_status_name;
            $msgData['msg_type'] = $msg->msg_type;
            $msgData['msg_type_main_name'] = MsgType::getMsgMainTypeName($msg->msg_type);
            $msgData['msg_type_name'] = MsgType::getViewItems()[$msg->msg_type] ?? '';
            $msgData['is_marked'] = $msg->is_marked;
            $msgData['post_time'] = $msg->create_time;
            $msgData['sender_id'] = $msg->sender_id;
            $msgData['root_msg_id'] = $msg->root_msg_id;
            $msgData['sender_is_home_pickup'] = '';
            $msgData['sender_number'] = '';

            if ($msg->sender_id == Msg::PLATFORM_SECRETARY_SENDER_OR_RECEIVER_ID) {
                $msgData['sender'] = 'Giga Help Desk';
            } elseif ($msg->sender_id == Msg::SYSTEM_SENDER_ID) {
                $msgData['sender'] = 'System';
            } elseif ($customerIdMap->offsetExists($msg->sender_id)) {
                /** @var Customer $customer */
                $customer = $customerIdMap->get($msg->sender_id);
                $msgData['sender'] = $customer->screenname ?: $customer->nickname;
                $msgData['sender_is_home_pickup'] = in_array($customer->customer_group_id, COLLECTION_FROM_DOMICILE);
                $msgData['sender_number'] = $customer->user_number;
                $msgData['ex_vat'] = VATToolTipWidget::widget(['customer' => $customer, 'is_show_vat' => false])->render();
            }

            $data[] = $msgData;
        }

        return $data;
    }

    protected function buildQuery()
    {
        $query = MsgReceive::queryRead()->alias('r')
            ->joinRelations('msg as s')
            ->where('r.receiver_id', $this->receiverId)
            ->where('r.send_type', $this->sendType);

        // 主题过滤
        if (trim($this->searchAttributes['filter_subject']) !== '') {
            $query = $query->where('s.title', 'like', '%' . $this->searchAttributes['filter_subject'] . '%');
        }

        // 时间过滤
        if ($this->searchAttributes['filter_post_time_from'] !== '') {
            $query = $query->where('r.create_time', '>=', $this->searchAttributes['filter_post_time_from']);
        }
        if ($this->searchAttributes['filter_post_time_to'] !=='') {
            $query = $query->where('r.create_time', '<', $this->searchAttributes['filter_post_time_to']);
        }

        // 是否已读过滤
        if ($this->searchAttributes['filter_read_status'] !== '') {
            $query = $query->where('r.is_read', $this->searchAttributes['filter_read_status']);
        }

        // 是否标记过滤
        if ($this->searchAttributes['filter_mark_status'] !== '') {
            $query = $query->where('r.is_marked', $this->searchAttributes['filter_mark_status']);
        }

        // 发件人过滤
        if (trim($this->searchAttributes['filter_sender']) !== '') {
            if (!$this->isSeller) {
                $customerIds = CustomerPartnerToCustomer::query()->where('screenname', 'like', '%' . $this->searchAttributes['filter_sender'] . '%')->pluck('customer_id')->toArray();
            } else {
                $customerIds = Customer::query()->where(function ($q) {
                    $q->where('nickname', 'like', '%' . $this->searchAttributes['filter_sender'] . '%')->orWhere('user_number', $this->searchAttributes['filter_sender']);
                })->pluck('customer_id')->toArray();
            }
            $query = $query->whereIn('s.sender_id', $customerIds);
        }

        // 回复状态过滤
        if ($this->searchAttributes['filter_replied_status'] !== '') {
            $query = $query->where('r.replied_status', $this->searchAttributes['filter_replied_status']);
        }

        // 删除状态过滤
        if ($this->searchAttributes['filter_delete_status'] !== '') {
            $query = $query->where('r.delete_status', $this->searchAttributes['filter_delete_status']);
        }

        // 消息类型过滤
        if (is_numeric($this->searchAttributes['filter_msg_type'])) {
            // 300 BID 特殊
            // 满足seller查询 范围性 100,200,300,400,500,600,700
            if ($this->searchAttributes['filter_msg_type'] != MsgType::BID && is_int($this->searchAttributes['filter_msg_type'] / 100)) {
                $query = $query->whereRaw('left(`s`.`msg_type`,1) = ' . substr($this->searchAttributes['filter_msg_type'], 0, 1));
            } else {
                $query = $query->where('s.msg_type', $this->searchAttributes['filter_msg_type']);
            }
        } elseif (is_array($this->searchAttributes['filter_msg_type'])) {
            $query = $query->whereIn('s.msg_type', $this->searchAttributes['filter_msg_type']);
        }
        return $query->select(['r.id', 'r.msg_id', 's.msg_type', 's.title', 'r.is_read', 'r.is_marked', 'r.create_time', 'r.replied_status', 's.sender_id', 's.root_msg_id']);
    }
}
