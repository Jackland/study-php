<?php

namespace App\Catalog\Forms\Message;

use App\Enums\Message\MsgDeleteStatus;
use App\Enums\Message\MsgReceiveReplied;
use App\Enums\Message\MsgType;
use App\Models\Buyer\BuyerToSeller;
use App\Models\CustomerPartner\CustomerPartnerToCustomer;
use App\Models\Message\MsgReceive;
use App\Repositories\Message\MessageRepository;
use App\Services\Message\MessageService;
use Framework\Exception\Exception;
use Framework\Model\RequestForm\RequestForm;

/**
 * 建立联系
 * Class EstablishContactForm
 * @package App\Catalog\Forms\Message
 */
class EstablishContactForm extends RequestForm
{
    public $msg_id;
    public $status;
    public $content;
    public $buyer_group_id;

    private $customerId;

    public function __construct()
    {
        parent::__construct();

        $this->customerId = customer()->getId();
    }

    /**
     * 验证
     * @return array
     */
    protected function getRules(): array
    {
        return [
            'msg_id' => ['required', function($attribute, $value, $fail) {
                 /** @var MsgReceive $msgReceive */
                $msgReceive = MsgReceive::queryRead()->with('msg')->where('msg_id', $value)->first();
                if (!$msgReceive || $msgReceive->receiver_id != $this->customerId) {
                    $fail('not found msg!');
                    return;
                }

                if ($msgReceive->msg->status != 100) {
                    $fail('msg status error!');
                    return;
                }

                if ($msgReceive->delete_status != MsgDeleteStatus::NOT_DELETED || $msgReceive->msg->delete_status != MsgDeleteStatus::NOT_DELETED) {
                    $fail('msg is deleted!');
                }
            }],
            'status' => 'required|in:0,1',
            'content' => 'required|string',
            'buyer_group_id' => ''
        ];
    }

    /**
     * @return int
     * @throws Exception
     * @throws \Throwable
     */
    public function handle(): int
    {
        if (!$this->isValidated()) {
            throw new Exception($this->getFirstError());
        }

        /** @var MsgReceive $msgReceive */
        $msgReceive = MsgReceive::queryRead()->with('msg')->where('msg_id', $this->msg_id)->first();
        $msg = $msgReceive->msg;
        $buyerId = $msgReceive->msg->sender_id;

        if (!app(MessageRepository::class)->isSameCountryFromSenderToReceivers($this->customerId, [$buyerId])) {
            throw new Exception('You are not able to establish contact or message communication with this Buyer since you are not in the same Country Market as the Buyer.', 400);
        }

        dbTransaction(function () use ($msg, $msgReceive, $buyerId) {
            if ($this->status == 1) {
                BuyerToSeller::query()->updateOrInsert([
                    'seller_id' => $this->customerId,
                    'buyer_id' => $buyerId,
                ], [
                    'buy_status' => 1,
                    'price_status' => 1,
                    'buyer_control_status' => 1,
                    'seller_control_status' => 1,
                    'discount' => 1,
                ]);

                if ($this->buyer_group_id) {
                    /** @var \ModelAccountCustomerpartnerBuyerGroup $modelBuyerGroup */
                    $modelBuyerGroup = load()->model('account/customerpartner/BuyerGroup');
                    $modelBuyerGroup->updateGroupLinkByBuyer($this->customerId, $buyerId, $this->buyer_group_id);
                }

                $msg->status = 101;
            } else {
                $msg->status = 102;
            }
            $msg->save();

            // 标记已回复
            $msgReceive->replied_status = MsgReceiveReplied::REPLIED;
            $msgReceive->save();

        });

        app(MessageService::class)->buildMsg($this->customerId, $this->getSubject(), $this->content, [], [$buyerId], MsgType::NORMAL, $this->msg_id);

        return $buyerId;
    }

    /**
     * @return string
     */
    private function getSubject(): string
    {
        $store = CustomerPartnerToCustomer::query()->where('customer_id', $this->customerId)->first();
        $storeName = $store->screenname;

        if ($this->status == 1) {
            return $storeName . ' has approved your application of establishing relationship.';
        }

        return $storeName . ' has rejected your application of establishing relationship.';
    }
}
