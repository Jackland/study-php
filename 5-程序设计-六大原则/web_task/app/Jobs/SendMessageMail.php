<?php

namespace App\Jobs;

use App\Helpers\LoggerHelper;
use App\Mail\MessageAlert;
use App\Models\Message\Message;
use App\Models\Message\Msg;
use App\Models\Message\MsgReceive;
use App\Repositories\Message\MessageRepository;
use App\Services\File\Tool\FileDeal;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendMessageMail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;
    public $timeout = 30;
    public $sleep = 60;
    public $msgId;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(int $msgId)
    {
        $this->msgId = $msgId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $receiverIds = MsgReceive::query()->where('msg_id', $this->msgId)->pluck('receiver_id')->toArray();

        /** @var Msg $msg */
        $msg = Msg::query()->with(['content'])->findOrFail($this->msgId);
        $msgContent = $msg->content;
        if (!$msgContent->exists) {
            return;
        }

        $emails = app(MessageRepository::class)->getSendEmails($msg->msg_type, $receiverIds);
        if (empty($emails)) {
            return;
        }

        $data['body'] = $msgContent->content;
        $data['subject'] = Message::mailFrom($msg->sender_id) . $msg->title;

        if ($msgContent->attach) {
            $attach = json_decode($msgContent->attach, true);
            foreach ($attach as $key => $item) {
                $data['attach'][$key]['url'] = url(['message/seller/download', 'filename' => $item['filename'], 'maskname' => $item['mask']]);
                $data['attach'][$key]['name'] = $item['mask'];
            }
        }
        if ($msgContent->attach_id) {
            // 新的文件处理方式-走JAVA接口查询和下载
            $fileTool = app(FileDeal::class);
            $files = $fileTool->getFileList($msgContent->attach_id);
            if ($files) {
                foreach ($files as $item) {
                    $data['attach'][] = [
                        'url' => $fileTool->getFileDownloadUrl($item['subId']),
                        'name' => $item['fileName'],
                    ];
                }
            }
        }

        foreach ($emails as $email) {
            try {
                \Mail::to($email)->send(new MessageAlert($data));
                LoggerHelper::logEmail([__CLASS__ => [
                    'to' => $email,
                    'result' => 'success',
                ]]);
            } catch (\Exception $e) {
                LoggerHelper::logEmail([__CLASS__ => [
                    'to' => $email,
                    'error' => $e->getMessage(),
                ]], 'error');
            }
        }
    }
}
