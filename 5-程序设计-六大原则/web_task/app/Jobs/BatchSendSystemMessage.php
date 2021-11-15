<?php

namespace App\Jobs;

use App\Enums\Message\MsgMsgType;
use App\Helpers\LoggerHelper;
use App\Models\Message\Msg;
use App\Services\Message\MessageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class BatchSendSystemMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 5;
    public $timeout = 300;
    public $sleep = 60;
    public $data;

    public function __construct($data = '')
    {
        $this->data = $data;
    }

    public function handle()
    {
        LoggerHelper::logSystemMessage('批量发送消息(新)-开始', 'info');

        $MessageService = app(MessageService::class);
        foreach ($this->data as $item) {
            $msgType = MsgMsgType::getTypeValue($item['msg_type']);
            $receiverIds = explode(',', $item['receiver_id']);

            $msg = new Msg();
            $msg->sender_id = 0;
            $msg->title = $item['subject'];
            $msg->is_sent = $item['is_sent'] ?? 1;
            $msg->msg_type = $msgType;
            $msg->operation_id = $item['operation_id'] ?? 0;
            $msg->receiver_group_ids = $item['receiver_group_ids'] ?? '';

            try {
                $MessageService->sendMsg($msg, $receiverIds, $item['body'], $item['attach_id'] ?? 0, $item['attach'] ?? '', $item['send_time'] ?? '', $item['is_send_email'] ?? 1);
            } catch (\Throwable $e) {
                LoggerHelper::logSystemMessage('批量发送消息(新)-处理失败' . $e->getMessage());
            }
        }

        LoggerHelper::logSystemMessage('批量发送消息(新)-结束', 'info');
    }
}
