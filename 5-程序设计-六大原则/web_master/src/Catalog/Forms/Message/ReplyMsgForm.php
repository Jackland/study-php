<?php

namespace App\Catalog\Forms\Message;

use App\Enums\Message\MsgDeleteStatus;
use App\Enums\Message\MsgReceiveReplied;
use App\Enums\Message\MsgType;
use App\Helper\SummernoteHtmlEncodeHelper;
use App\Models\Message\Msg;
use App\Models\Message\MsgReceive;
use App\Repositories\Message\MessageRepository;
use App\Services\Message\MessageService;
use Framework\Exception\Exception;
use Framework\Model\RequestForm\RequestForm;
use Throwable;

class ReplyMsgForm extends RequestForm
{
    public $msg_id;
    public $receiver_id;
    public $subject;
    public $content;

    private $senderId;

    public function __construct()
    {
        parent::__construct();
        $this->senderId = customer()->getId();
    }

    /**
     * 验证
     * @return array
     */
    protected function getRules(): array
    {
        return [
            'msg_id' => 'required|integer',
            'receiver_id' => 'required|integer',
            'subject' => 'required|string',
            'content' => ['required', function($attribute, $value, $fail) {
                $this->content = SummernoteHtmlEncodeHelper::decode($value, true);
                if (empty(str_replace(['&nbsp;', ' ', '　'], '', strip_tags($this->content, '<img>')))) {
                    $fail('Content is required field, can not be blank.');
                }
            }],
        ];
    }

    /**
     * 回复
     * @throws Exception
     * @throws Throwable
     */
    public function reply()
    {
        if (!$this->isValidated()) {
            throw new Exception($this->getFirstError(), 400);
        }

        /** @var Msg $msg */
        $msg = Msg::queryRead()->with(['receives'])->where('id', $this->msg_id)->first();
        if (!$msg) {
            throw new Exception('not found!');
        }

        if ($msg->sender_id == $this->senderId && !$msg->receives->where('receiver_id', $this->receiver_id)->first()) {
            throw new Exception('not found!');
        }

        if ($msg->sender_id == $this->receiver_id && !$msg->receives->where('receiver_id', $this->senderId)->first()) {
            throw new Exception('not found!');
        }

        // 相同国别的才能对话
        if ($this->receiver_id > 0 && !app(MessageRepository::class)->isSameCountryFromSenderToReceivers($this->senderId, [$this->receiver_id])) {
            throw new Exception('You are not able to establish contact or message communication with this Buyer since you are not in the same Country Market as the Buyer.', 400);
        }

        if ($this->receiver_id > 0) {
            [, $receiverIds] = app(MessageRepository::class)->getMsgLanguageAndReceiverIds([$this->receiver_id], $this->subject, $this->content);
            // 提示后无法点击下一步发送 code:420
            if (empty($receiverIds)) {
                throw new Exception("The language of your message does not match the languages acceptable set by the recipient. Please change the language and try again. ", 420);
            }
        } else {
            $receiverIds = [$this->receiver_id];
        }

        // 保存的逻辑
        $files = request()->filesBag->get('files', []);
        app(MessageService::class)->buildMsg($this->senderId, $this->subject, $this->content, $files, $receiverIds, MsgType::NORMAL, $msg->root_msg_id);

        // 回复状态
        if ($msg->sender_id == $this->receiver_id) {
            MsgReceive::query()->alias('r')
                ->joinRelations('msg as s')
                ->where('r.receiver_id', $this->senderId)
                ->where('s.sender_id', $msg->sender_id)
                ->where('s.root_msg_id', $msg->root_msg_id)
                ->update(['r.replied_status' => MsgReceiveReplied::REPLIED]);
        }

    }
}
