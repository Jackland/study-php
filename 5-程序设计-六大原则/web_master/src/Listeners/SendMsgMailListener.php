<?php

namespace App\Listeners;

use App\Components\RemoteApi;
use App\Listeners\Events\SendMsgMailEvent;
use App\Logging\Logger;
use App\Models\Message\Msg;
use App\Repositories\Customer\CustomerRepository;
use App\Repositories\Message\MessageRepository;
use App\Services\Message\MessageService;
use Symfony\Component\HttpClient\HttpClient;

class SendMsgMailListener
{
    public function handle(SendMsgMailEvent $event)
    {
        /** @var Msg $msg */
        $msg = Msg::query()->with('content')->findOrFail($event->msgId);
        $this->sendMail($msg, $event->receiverIds);
    }

    /**
     * 发送邮件
     * @param Msg $msg
     * @param array $receiverIds
     */
    public function sendMail(Msg $msg, array $receiverIds)
    {
        $emails = app(MessageRepository::class)->getSendEmails($msg->msg_type, $receiverIds);
        if (empty($emails)) {
            return;
        }

        $msgContent = $msg->content;
        if (!$msgContent->exists) {
            return;
        }

        $messageService = app(MessageService::class);

        $data['body'] = $messageService->replaceMsgContent($msgContent->content, $msg->msg_type);
        $data['subject'] = $this->mailFrom($msg->sender_id) . $messageService->replaceMsgTitle($msg->title, $msg->msg_type, $msgContent->content);
        $data['to'] = $emails;

        if ($msgContent->attach) {
            $attach = json_decode($msgContent->attach, true);
            foreach ($attach as $key => $item) {
                $data['attach'][$key]['url'] = url(['message/seller/download', 'filename' => $item['filename'], 'maskname' => $item['mask']]);
                $data['attach'][$key]['name'] = $item['mask'];
            }
        }
        if ($msgContent->attach_id) {
            $files = RemoteApi::file()->getByMenuId($msgContent->attach_id);
            foreach ($files as $file) {
                $data['attach'][] = [
                    'url' => RemoteApi::file()->getOutFileDownloadUrl((int)$file->subId),
                    'name' => $file->fileName,
                ];
            }
        }

        try {
            $client = HttpClient::create();
            $url = URL_TASK_WORK . '/api/email/send';
            $client->request('POST', $url, [
                'body' => $data,
            ]);
        } catch (\Throwable $e) {
            Logger::error('message send mail error:' . $e->getMessage());
        }
    }

    /**
     * @param int $senderId
     * @return string
     */
    private function mailFrom(int $senderId): string
    {
        if (in_array($senderId, GIGACLOUD_PLATFROM_SELLER)) {
            return '[From GIGACLOUD]';
        }

        if ($senderId == Msg::SYSTEM_SENDER_ID) {
            return '[From GIGACLOUD]';
        }

        if ($senderId == Msg::PLATFORM_SECRETARY_SENDER_OR_RECEIVER_ID) {
            return '[From GIGACLOUD]';
        }

        if (app(CustomerRepository::class)->checkIsSeller($senderId)) {
            return '[From Seller]';
        } else {
            return '[From Buyer]';
        }
    }
}
