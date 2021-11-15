<?php

namespace App\Services\Message;

use App\Components\BatchInsert;
use App\Enums\Common\YesNoEnum;
use App\Enums\Message\MsgMsgMode;
use App\Enums\Message\MsgReceiveSendType;
use App\Enums\Message\MsgReceiveType;
use App\Jobs\SendMessageMail;
use App\Models\Message\Msg;
use App\Models\Message\MsgContent;
use App\Models\Message\MsgReceive;
use App\Models\Message\MsgTask;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Arr;

class MessageService
{
    const SYSTEM_ID = 0; // 系统ID标识
    const PLATFORM_SECRETARY = -1; // 平台小秘书ID标识

    /**
     * @param array $receiverIds 接受者数组IDS
     * @param int $msgId 消息ID
     * @param int $sendType 发送方类型
     * @param string $nowDate 时间
     */
    private function batchInsertMsgReceive(array $receiverIds, int $msgId, int $sendType, string $nowDate)
    {
        $batchInsert = new BatchInsert();
        $batchInsert->begin(MsgReceive::class);
        foreach ($receiverIds as $receiverId) {
            $batchInsert->addRow([
                'msg_id' => $msgId,
                'receiver_id' => $receiverId,
                'send_type' => $sendType,
                'create_time' => $nowDate
            ]);
        }
        $batchInsert->end();
    }

    /**
     * 获取接收者类型
     *
     * @param int $senderId 接受者类型
     * @return int
     */
    private function getSenderType(int $senderId): int
    {
        return  $senderId == self::SYSTEM_ID ? MsgReceiveSendType::SYSTEM : ($senderId == self::PLATFORM_SECRETARY ? MsgReceiveSendType::PLATFORM_SECRETARY : MsgReceiveSendType::CUSTOMER);
    }

    /**
     *
     * 发送邮件
     * @param Msg $msg
     * @param array|int $receiverIds
     * @param string $content
     * @param int $attachId
     * @param string $attach
     * @param string $sendTime
     * @param int $isSendMail
     * @return int
     * @throws Exception
     */
    public function sendMsg(Msg $msg, $receiverIds, string $content, int $attachId = 0,  string $attach = '', string $sendTime = '', int $isSendMail = 1): int
    {
        if ($msg->id) {
            throw new Exception('站内信已存在');
        }

        $receiverIds = Arr::wrap($receiverIds);
        $parentMsg = null;
        if (!empty($msg->parent_msg_id)) {
            $parentMsg = Msg::query()->findOrFail($msg->parent_msg_id);
        }

        $msg->receive_type = (count($receiverIds) == 1 && $receiverIds[0] == self::PLATFORM_SECRETARY) ? MsgReceiveType::PLATFORM_SECRETARY : MsgReceiveType::CUSTOMER;
        $msg->msg_mode = count($receiverIds) > 1 ? MsgMsgMode::MASS_TEXTING : MsgMsgMode::PRIVATE_CHAT;

        // 定时发送的消息创建时间修改
        if (!$msg->is_sent) {
            $msg->create_time = $sendTime;
        }

        try {
            \DB::beginTransaction();
            if ($parentMsg instanceof Msg) {
                $msg->root_msg_id = $parentMsg->root_msg_id;
            } else {
                $msg->save();
                $msg->root_msg_id = $msg->id;
            }
            $msg->save();
            $msgId = $msg->id;

            MsgContent::query()->insert([
                'msg_id' => $msgId,
                'content' => trim($content),
                'attach_id' => $attachId,
                'attach' => $attach
            ]);

            if ($msg->is_sent) {
                $this->batchInsertMsgReceive($receiverIds, $msgId, $this->getSenderType($msg->sender_id), Carbon::now()->toDateTimeString());
            } else {
                MsgTask::query()->insert([
                    'msg_id' => $msgId,
                    'send_time' => $sendTime,
                    'is_sent' => YesNoEnum::NO,
                    'receiver_ids' => join(',', $receiverIds),
                ]);
            }

            \DB::commit();
        } catch (Exception $e) {
            \DB::rollBack();
            throw new Exception($e->getMessage());
        }

        // 发送邮件
        if ($isSendMail) {
            SendMessageMail::dispatch($msgId)->onQueue('send_message_mail');
        }

        return $msgId;
    }
}