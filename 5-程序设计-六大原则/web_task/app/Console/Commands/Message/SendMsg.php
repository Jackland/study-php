<?php

namespace App\Console\Commands\Message;

use App\Components\BatchInsert;
use App\Enums\Common\YesNoEnum;
use App\Enums\Message\MsgReceiveSendType;
use App\Jobs\SendMessageMail;
use App\Models\Message\MsgReceive;
use App\Models\Message\MsgTask;
use App\Services\Message\MessageService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Log;

class SendMsg extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'message:send-msg';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '定时发送消息';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $msgTasks = MsgTask::query()
            ->with(['msg'])
            ->where('is_sent', YesNoEnum::NO)
            ->where('send_time', '<=', Carbon::now()->toDateTimeString())
            ->get();

        foreach ($msgTasks as $msgTask) {
            /** @var MsgTask $msgTask */

            $msg = $msgTask->msg;

            $receiverIds = explode(',', $msgTask->receiver_ids);
            // 已删的或者已发送的或没有接受者的不需再次发送
            if ($msgTask->msg->delete_status != 0 || $msgTask->msg->is_sent == YesNoEnum::YES || empty($receiverIds)) {
                $msgTask->is_sent = YesNoEnum::YES;
                $msgTask->save();

                $msg->is_sent = YesNoEnum::YES;
                $msg->save();
            }

            $msgId = $msgTask->msg_id;
            $msgSenderId = $msgTask->msg->sender_id;
            $createTime = Carbon::now()->toDateTimeString();
            if ($msgSenderId == MessageService::PLATFORM_SECRETARY) {
                $sendType = MsgReceiveSendType::PLATFORM_SECRETARY;
            } elseif ($msgSenderId == MessageService::SYSTEM_ID) {
                $sendType = MsgReceiveSendType::SYSTEM;
            } else {
                $sendType = MsgReceiveSendType::CUSTOMER;
            }

            $batchInsert = new BatchInsert();
            $batchInsert->begin(MsgReceive::class);

            try {
                \DB::beginTransaction();
                foreach ($receiverIds as $receiverId) {
                    $batchInsert->addRow([
                        'msg_id' => $msgId,
                        'receiver_id' => $receiverId,
                        'send_type' => $sendType,
                        'create_time' => $createTime
                    ]);
                }
                $batchInsert->end();

                $msgTask->is_sent = YesNoEnum::YES;
                $msgTask->save();

                $msg->is_sent = YesNoEnum::YES;
                $msg->save();

                \DB::commit();

                echo "{$msgTask->msg_id} 发送成功";
            } catch (\Exception $e) {
                \DB::rollBack();

                Log::error("定时任务处理发送消息失败, 消息ID:{$msgTask->msg_id}; 失败原因：{$e->getMessage()}" );
            }

            // 发送邮件
            SendMessageMail::dispatch($msgId)->onQueue('send_message_mail');
        }
    }
}
