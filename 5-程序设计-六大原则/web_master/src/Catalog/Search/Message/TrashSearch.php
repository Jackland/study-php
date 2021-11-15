<?php

namespace App\Catalog\Search\Message;

use App\Enums\Common\YesNoEnum;
use App\Enums\Message\MsgDeleteStatus;
use App\Models\Customer\Customer;
use App\Models\CustomerPartner\CustomerPartnerToCustomer;
use App\Models\Message\Msg;
use App\Models\Message\MsgReceive;
use App\Models\Message\Notice;
use App\Models\Message\StationLetterCustomer;
use Framework\DataProvider\Paginator;
use Framework\DataProvider\QueryDataProvider;
use Framework\DataProvider\SearchModelTrait;
use Framework\DataProvider\Sort;
use Illuminate\Database\Eloquent\Collection;

class TrashSearch
{
    use SearchModelTrait;

    private $receiverId;

    private $searchAttributes = [
        'filter_subject' => '',
        'filter_post_time_from' => '',
        'filter_post_time_to' => '',
    ];

    /**
     * @param $params
     * @return mixed
     * @throws \Framework\Exception\InvalidConfigException
     */
    public function get($params)
    {
        $this->loadAttributes($params);

        $query = $this->buildQuery();

        $dataProvider = new QueryDataProvider($query);

        $dataProvider->setSort(new Sort([
            'defaultOrder' => ['post_time' => SORT_DESC],
            'rules' => [
                'post_time' => 'post_time',
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
        // 获取发送的站内信
        $senderMessages = $list->where('type', 'send_message')->pluck('message_id')->toArray();
        $senderReceiveMessagesCustomerIds = [];
        $senderReceiveMessagesIdMap = [];
        if (!empty($senderMessages)) {
            // 获取发送站内信的接收方
            $senderReceiveMessages = MsgReceive::query()->whereIn('msg_id', $senderMessages)->groupBy(['msg_id'])->select(['msg_id', 'receiver_id'])->selectRaw('count(1) as count')->get();
            $senderReceiveMessagesIdMap = $senderReceiveMessages->keyBy('msg_id')->toArray();
            $senderReceiveMessagesCustomerIds = $senderReceiveMessages->where('receiver_id', '>', 0)->pluck('receiver_id')->toArray();
        }

        // 获取接受的站内信发送人的ID
        $receiveMessagesCustomerIds = $list->where('type', 'receive_message')->where('customer_id', '>', 0)->pluck('customer_id')->toArray();
        if (\customer()->isPartner()) {
            $customerIdNameMap = Customer::query()->whereIn('customer_id', array_merge($senderReceiveMessagesCustomerIds, $receiveMessagesCustomerIds))->pluck('nickname', 'customer_id');
        } else {
            $customerIdNameMap = CustomerPartnerToCustomer::query()->whereIn('customer_id', array_merge($senderReceiveMessagesCustomerIds, $receiveMessagesCustomerIds))->pluck('screenname', 'customer_id');
        }

        $data = $list->toArray();
        foreach ($data as &$v) {
            if (\customer()->isPartner()) {
                $v['detail_url'] = url(['customerpartner/message_center/message/detail', 'msg_id' => $v['message_id']]);
            } else {
                $v['detail_url'] = url(['account/message_center/message/detail', 'msg_id' => $v['message_id']]);
            }

            if ($v['customer_id'] == Msg::PLATFORM_SECRETARY_SENDER_OR_RECEIVER_ID) {
                $v['customer_name'] = 'Giga Help Desk ';
            }
            if ($v['customer_id'] == Msg::SYSTEM_SENDER_ID) {
                $v['customer_name'] = 'System';
            }
            if ($v['type'] == 'notice' || $v['type'] == 'station_letter') {
                if (\customer()->isPartner()) {
                    $v['detail_url'] = url(['customerpartner/message_center/notice/detail', 'notice_id' => $v['message_id'], 'type' => $v['type']]);
                } else {
                    $v['detail_url'] = url(['account/message_center/platform_notice/view', 'notice_id' => $v['message_id'], 'type' => $v['type']]);
                }

                $v['customer_name'] = '	Marketplace';
            }

            if ($v['type'] == 'receive_message' && isset($customerIdNameMap[$v['customer_id']])) {
                $v['customer_name'] = $customerIdNameMap[$v['customer_id']];
            }

            if ($v['type'] == 'send_message' && isset($senderReceiveMessagesIdMap[$v['message_id']])) {
                $senderReceiveMessage = $senderReceiveMessagesIdMap[$v['message_id']];
                $v['customer_id'] = $senderReceiveMessage['receiver_id'];
                if ($senderReceiveMessage['receiver_id'] == Msg::SYSTEM_SENDER_ID) {
                    $v['customer_name'] = 'System';
                } elseif ($senderReceiveMessage['receiver_id'] == Msg::PLATFORM_SECRETARY_SENDER_OR_RECEIVER_ID) {
                    $v['customer_name'] = 'Giga Help Desk';
                } else {
                    if ($senderReceiveMessage['count'] == 1) {
                        $v['customer_name'] = $customerIdNameMap[$senderReceiveMessage['receiver_id']] ?? '';
                    } else {
                        $v['customer_name'] = ($customerIdNameMap[$senderReceiveMessage['receiver_id']] ?? '') . '等' . $senderReceiveMessage['count'] . '人';
                    }
                }
            }
        }
        unset($v);

        return  $data;
    }

    /**
     * @return \Framework\Model\Eloquent\Builder
     */
    protected function buildQuery()
    {
        $customerId = customer()->getId();

        $noticeQuery = Notice::queryRead()
            ->alias('n')
            ->joinRelations('placeholder as p')
            ->where('p.customer_id', $customerId)
            ->where('p.is_del', YesNoEnum::YES)
            ->when(!empty(trim($this->searchAttributes['filter_subject'])), function ($q) {
                $q->where('n.title', 'like', '%' . trim($this->searchAttributes['filter_subject']) . '%');
            })
            ->when(!empty($this->searchAttributes['filter_post_time_from']), function ($q) {
                $q->where('n.publish_date', '>=', $this->searchAttributes['filter_post_time_from']);
            })
            ->when(!empty($this->searchAttributes['filter_post_time_to']), function ($q) {
                $q->where('n.publish_date', '<', $this->searchAttributes['filter_post_time_to']);
            })
            ->selectRaw("'notice' as `type`, n.id as message_id, '0' as customer_id, n.title, p.is_read, n.publish_date as post_time, n.make_sure_status, p.make_sure_status as p_make_sure_status, n.id as primary_key_id");


        $receiveQuery = MsgReceive::queryRead()
            ->alias('r')
            ->joinRelations('msg as s')
            ->where('r.receiver_id', $customerId)
            ->where('r.delete_status', MsgDeleteStatus::TRASH)
            ->when(!empty(trim($this->searchAttributes['filter_subject'])), function ($q) {
                $q->where('s.title', 'like', '%' . trim($this->searchAttributes['filter_subject']) . '%');
            })
            ->when(!empty($this->searchAttributes['filter_post_time_from']), function ($q) {
                $q->where('r.create_time', '>=', $this->searchAttributes['filter_post_time_from']);
            })
            ->when(!empty($this->searchAttributes['filter_post_time_to']), function ($q) {
                $q->where('r.create_time', '<', $this->searchAttributes['filter_post_time_to']);
            })
            ->selectRaw("'receive_message' as `type`, r.msg_id as message_id, s.sender_id as customer_id, s.title, r.is_read, r.create_time as post_time, '0', '0', r.id as primary_key_id ");

        $sendQuery = Msg::queryRead()
            ->where('sender_id', $customerId)
            ->where('delete_status', MsgDeleteStatus::TRASH)
            ->when(!empty(trim($this->searchAttributes['filter_subject'])), function ($q) {
                $q->where('title', 'like', '%' . trim($this->searchAttributes['filter_subject']) . '%');
            })
            ->when(!empty($this->searchAttributes['filter_post_time_from']), function ($q) {
                $q->where('create_time', '>=', $this->searchAttributes['filter_post_time_from']);
            })
            ->when(!empty($this->searchAttributes['filter_post_time_to']), function ($q) {
                $q->where('create_time', '<', $this->searchAttributes['filter_post_time_to']);
            })
            ->selectRaw("'send_message' as `type`, id as message_id, '0' as customer_id, title, '1', create_time as post_time, '0', '0', id as primary_key_id");


        $stationLetterCustomerQuery = StationLetterCustomer::queryRead()
            ->alias('slc')
            ->leftJoinRelations('stationLetter as sl')
            ->where('slc.customer_id', $customerId)
            ->where('slc.is_delete', YesNoEnum::YES)
            ->where('sl.status', YesNoEnum::YES)
            ->where('sl.is_delete', YesNoEnum::NO)
            ->when(!empty(trim($this->searchAttributes['filter_subject'])), function ($q) {
                $q->where('sl.title', 'like', '%' . trim($this->searchAttributes['filter_subject']) . '%');
            })
            ->when(!empty($this->searchAttributes['filter_post_time_from']), function ($q) {
                $q->where('sl.send_time', '>=', $this->searchAttributes['filter_post_time_from']);
            })
            ->when(!empty($this->searchAttributes['filter_post_time_to']), function ($q) {
                $q->where('sl.send_time', '<', $this->searchAttributes['filter_post_time_to']);
            })
            ->selectRaw("'station_letter' as `type`, sl.id as message_id, '0' as customer_id, sl.title, slc.is_read, sl.send_time as post_time, '0', '0', sl.id as primary_key_id");

        return $noticeQuery->union($receiveQuery)->union($sendQuery)->union($stationLetterCustomerQuery);
    }
}
