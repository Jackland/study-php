<?php

namespace App\Catalog\Forms\Message;

use App\Enums\Common\YesNoEnum;
use App\Enums\Message\MsgDeleteStatus;
use App\Enums\Message\MsgReceiveReplied;
use App\Enums\Message\MsgType;
use App\Models\Customer\Customer;
use App\Models\CustomerPartner\CustomerPartnerToCustomer;
use App\Models\Message\Msg;
use App\Models\Message\MsgReceive;
use Framework\Exception\Exception;
use Framework\Model\RequestForm\RequestForm;

class SetupAllMsgForm extends RequestForm
{
    private $isSeller;

    public $tab_type;
    public $setup_type;
    public $exclude_ids = '';
    public $user_type;

    private $searchAttributes = [
        'filter_subject' => '',
        'filter_post_time_from' => '',
        'filter_post_time_to' => '',
        'filter_read_status' => '',
        'filter_mark_status' => '',
        'filter_sender' => '',
        'filter_receiver' => '',
        'filter_msg_type' => '',
        'filter_replied_status' => '',
    ];

    public function __construct()
    {
        parent::__construct();

        $this->isSeller = customer()->isPartner();

        foreach ($this->request->attributes->all() as $key => $value) {
            if (array_key_exists($key, $this->searchAttributes)) {
                $this->searchAttributes[$key] = $value;
            }
        }

        // 300 bid 特殊处理
        if (request('msg_type', '') == MsgType::BID && empty($this->searchAttributes['filter_msg_type'])) {
            $this->searchAttributes = MsgType::mainTypeMap()[MsgType::BID];
        }
    }

    /**
     * 验证
     * @return array
     */
    protected function getRules(): array
    {
        return [
            'tab_type' => 'required|in:sent,inbox',
            'setup_type' => 'required|in:1,2,3,4,5,6,7',
            'exclude_ids' => '',
            'user_type' => 'required|in:1,2,3'
        ];
    }

    /**
     * @return void
     * @throws Exception
     */
    public function handle()
    {
        if (!$this->isValidated()) {
            throw new Exception($this->getFirstError());
        }

        $update = $this->getUpdate();
        if (empty($update)) {
            return;
        }

        switch ($this->tab_type) {
            case 'sent':
                $this->handleSent($update);
                break;
            case 'inbox':
                $this->handleInbox($update);
                break;
        }
    }

    /**
     * @param array $update
     */
    private function handleInbox(array $update)
    {
        $query = MsgReceive::query()->alias('r')
            ->joinRelations('msg as s')
            ->where('r.receiver_id', customer()->getId())
            ->where('r.delete_status', MsgDeleteStatus::NOT_DELETED)
            ->where('r.send_type', $this->user_type);

        // 主题过滤
        if (trim($this->searchAttributes['filter_subject']) !== '') {
            $query = $query->where('s.title', 'like', '%' . $this->searchAttributes['filter_subject'] . '%');
        }

        // 时间过滤
        if ($this->searchAttributes['filter_post_time_from'] !== '') {
            $query = $query->where('r.create_time', '>=', $this->searchAttributes['filter_post_time_from']);
        }
        if ($this->searchAttributes['filter_post_time_to'] !== '') {
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
            if (!empty($customerIds)) {
                $query = $query->whereIn('s.senderId', $customerIds);
            }
        }

        // 回复状态过滤
        if ($this->searchAttributes['filter_replied_status'] !== '') {
            // filter_replied_status 的特殊处理 5=>0, 6=>1, 7=>2
            $filterRepliedStatusMap = [
                '5' => '0',
                '6' => '1',
                '7' => '2',
            ];
            if (isset($filterRepliedStatusMap[$this->searchAttributes['filter_replied_status']])) {
                $query = $query->where('r.replied_status', $filterRepliedStatusMap[$this->searchAttributes['filter_replied_status']]);
            }
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

        if (!empty(request('exclude_ids'))) {
            $query = $query->whereNotIn('r.id', explode(',', request('exclude_ids')));
        }

        $query->update($update);
    }

    /**
     * @param array $update
     */
    private function handleSent(array $update)
    {
        $query = Msg::query()->alias('s')
            ->joinRelations('receives as r')
            ->where('s.sender_id', customer()->getId())
            ->where('s.delete_status', MsgDeleteStatus::NOT_DELETED)
            ->where('s.receive_type', $this->user_type);

        // 收件人过滤
        if (trim($this->searchAttributes['filter_receiver']) != '') {
            if (!$this->isSeller) {
                $customerIds = CustomerPartnerToCustomer::query()->where('screenname', 'like', '%' . $this->searchAttributes['filter_receiver'] . '%')->pluck('customer_id')->toArray();
            } else {
                $customerIds = Customer::query()->where(function ($q) {
                    $q->where('nickname', 'like', '%' . $this->searchAttributes['filter_receiver'] . '%')->orWhere('user_number', $this->searchAttributes['filter_receiver']);
                })->pluck('customer_id')->toArray();
            }
            if (!empty($customerIds)) {
                $query->whereIn('r.receiver_id', $customerIds);
            }
        }

        // 主题过滤
        if (trim($this->searchAttributes['filter_subject']) != '') {
            $query = $query->where('s.title', 'like', '%' . $this->searchAttributes['filter_subject'] . '%');
        }

        // 时间过滤
        if ($this->searchAttributes['filter_post_time_from'] != '') {
            $query = $query->where('s.create_time', '>=', $this->searchAttributes['filter_post_time_from']);
        }
        if ($this->searchAttributes['filter_post_time_to'] != '') {
            $query = $query->where('s.create_time', '<', $this->searchAttributes['filter_post_time_to']);
        }

        // 是否标记过滤
        if ($this->searchAttributes['filter_mark_status'] != '') {
            $query = $query->where('s.is_marked', $this->searchAttributes['filter_mark_status']);
        }

        if (!empty(request('exclude_ids'))) {
            $query = $query->whereNotIn('s.id', explode(',', request('exclude_ids')));
        }

        $query->update($update);
    }

    /**
     * @return array
     */
    private function getUpdate(): array
    {
        $update = [];
        if ($this->tab_type == 'sent') {
            switch ($this->setup_type) {
                case 3:
                    $update = ['s.is_marked' => YesNoEnum::NO];
                    break;
                case 4:
                    $update = ['s.is_marked' => YesNoEnum::YES];
                    break;
            }
        } elseif ($this->tab_type == 'inbox') {
            switch ($this->setup_type) {
                case 1:
                    $update = ['r.is_read' => YesNoEnum::NO];
                    break;
                case 2:
                    $update = ['r.is_read' => YesNoEnum::YES];
                    break;
                case 3:
                    $update = ['r.is_marked' => YesNoEnum::NO];
                    break;
                case 4:
                    $update = ['r.is_marked' => YesNoEnum::YES];
                    break;
                case 5:
                    $update = ['r.replied_status' => MsgReceiveReplied::NO_REPLY];
                    break;
                case 6:
                    $update = ['r.replied_status' => MsgReceiveReplied::REPLIED];
                    break;
                case 7:
                    $update = ['r.replied_status' => MsgReceiveReplied::NOT_HANDLE];
                    break;
            }
        }

        return $update;
    }
}
