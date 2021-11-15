<?php

namespace App\Catalog\Search\Message;

use App\Enums\Message\MsgMode;
use App\Enums\Message\MsgReceiveType;
use App\Models\Customer\Customer;
use App\Models\CustomerPartner\CustomerPartnerToCustomer;
use App\Models\Message\Msg;
use App\Models\Message\MsgReceive;
use App\Services\Message\MessageService;
use App\Widgets\VATToolTipWidget;
use Framework\DataProvider\Paginator;
use Framework\DataProvider\QueryDataProvider;
use Framework\DataProvider\SearchModelTrait;
use Framework\DataProvider\Sort;
use Illuminate\Database\Eloquent\Collection;

class SentSearch
{
    use SearchModelTrait;

    private $senderId;
    private $receiveType;
    private $isSeller;

    private $searchAttributes = [
        'filter_subject' => '',
        'filter_post_time_from' => '',
        'filter_post_time_to' => '',
        'filter_mark_status' => '',
        'filter_receiver' => '',
        'filter_delete_status' => '',
    ];

    /**
     * MessageSearch constructor.
     * @param int $senderId
     * @param int $receiveType
     */
    public function __construct(int $senderId, int $receiveType = MsgReceiveType::USER)
    {
        $this->senderId = $senderId;
        $this->receiveType = $receiveType;
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
                'id' => 'id',
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
        $data = [];
        foreach ($list as $msg) {
            /** @var Msg $msg */
            $msgData['id'] = $msg->id;
            $msgData['msg_id'] = $msg->id;
            $msgData['subject'] = app(MessageService::class)->replaceMsgTitle($msg->title, $msg->msg_type);
            $msgData['msg_mode_name'] = $msg->msg_mode_name;
            $msgData['msg_mode'] = $msg->msg_mode;
            $msgData['is_marked'] = $msg->is_marked;
            $msgData['post_time'] = $msg->create_time;
            $msgData['root_msg_id'] = $msg->root_msg_id;
            /** @var MsgReceive $receive */
            $receive = $msg->receives->first();
            if ($msg->msg_mode == MsgMode::PRIVATE_CHAT) {
                $msgData['recipient'] = $receive->receiver_id == Msg::PLATFORM_SECRETARY_SENDER_OR_RECEIVER_ID ? 'Giga Help Desk' : ($receive->receiver->seller ? $receive->receiver->seller->screenname : $receive->receiver->nickname);
                $msgData['receiver_id'] = $receive->receiver_id;
                $msgData['receiver_number'] = $receive->receiver_id == Msg::PLATFORM_SECRETARY_SENDER_OR_RECEIVER_ID ? '' : $receive->receiver->user_number;
                $msgData['receiver_is_home_pickup'] = $receive->receiver_id == Msg::PLATFORM_SECRETARY_SENDER_OR_RECEIVER_ID ? '' : in_array($receive->receiver->customer_group_id, COLLECTION_FROM_DOMICILE);
                $msgData['ex_vat'] = VATToolTipWidget::widget(['customer' => $receive->receiver, 'is_show_vat' => false])->render();
                $msgData['receives'] = [];
            } else {
                $msgData['recipient'] = "Total {$msg->receives->count()} recipients including " . ($receive->receiver->seller ? $receive->receiver->seller->screenname : $receive->receiver->nickname);
                $msgData['receiver_id'] = '';
                $msgData['receiver_number'] = '';
                $msgData['receiver_is_home_pickup'] = '';
                $msgData['receives'] = $msg->receives;
            }

            $data[] = $msgData;
        }

        return $data;
    }

    protected function buildQuery()
    {
        $query = Msg::queryRead()->with(['receives', 'receives.receiver', 'receives.receiver.seller', 'receives.receiver.buyer'])
            ->where('sender_id', $this->senderId)
            ->where('receive_type', $this->receiveType);

        // 收件人过滤
        if (trim($this->searchAttributes['filter_receiver']) !== '') {
            if (!$this->isSeller) {
                $customerIds = CustomerPartnerToCustomer::query()->where('screenname', 'like', '%' . $this->searchAttributes['filter_receiver'] . '%')->pluck('customer_id')->toArray();
            } else {
                $customerIds = Customer::query()->where(function ($q) {
                    $q->where('nickname', 'like', '%' . $this->searchAttributes['filter_receiver'] . '%')->orWhere('user_number', $this->searchAttributes['filter_receiver']);
                })->pluck('customer_id')->toArray();
            }
            $query = $query->whereHas('receives', function ($q) use ($customerIds) {
                $q->whereIn('receiver_id', $customerIds);
            });
        }

        // 主题过滤
        if (trim($this->searchAttributes['filter_subject']) !== '') {
            $query =  $query->where('title', 'like', '%' . $this->searchAttributes['filter_subject'] . '%');
        }

        // 时间过滤
        if ($this->searchAttributes['filter_post_time_from'] !== '') {
            $query = $query->where('create_time', '>=', $this->searchAttributes['filter_post_time_from']);
        }
        if ($this->searchAttributes['filter_post_time_to'] !== '') {
            $query = $query->where('create_time', '<', $this->searchAttributes['filter_post_time_to']);
        }

        // 是否标记过滤
        if ($this->searchAttributes['filter_mark_status'] !== '') {
            $query = $query->where('is_marked', $this->searchAttributes['filter_mark_status']);
        }

        // 删除状态过滤
        if ($this->searchAttributes['filter_delete_status'] !== '') {
            $query = $query->where('delete_status', $this->searchAttributes['filter_delete_status']);
        }

        return $query;
    }
}
