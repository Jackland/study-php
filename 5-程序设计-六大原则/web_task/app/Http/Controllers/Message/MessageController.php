<?php
/**
 * Created by PhpStorm.
 * User: jun
 * Date: 2020/01/11
 * Time: 9:31
 */

namespace App\Http\Controllers\Message;

use App\Helpers\ApiResponse;
use App\Helpers\LoggerHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\BatchSendSystemMessagePost;
use App\Http\Requests\SendMsgRequest;
use App\Jobs\BatchSendSystemMessage;
use App\Jobs\SendMail;
use App\Models\Message\Message;
use App\Models\Message\Msg;
use App\Models\Message\StationLetter;
use App\Services\Message\MessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;


class MessageController extends Controller
{
    use ApiResponse;

    /**
     * @param SendMsgRequest $sendMsgRequest
     * @return JsonResponse
     */
    public function sendMsg(SendMsgRequest $sendMsgRequest): JsonResponse
    {
        $msg = new Msg();
        $msg->sender_id = $sendMsgRequest->sender_id;
        $msg->title = $sendMsgRequest->subject;
        $msg->is_sent = $sendMsgRequest->is_send;
        $msg->parent_msg_id = $sendMsgRequest->parent_msg_id ?? 0;
        $msg->operation_id = $sendMsgRequest->operation_id ?? 0;
        $msg->receiver_group_ids = $sendMsgRequest->receiver_group_ids ?? '';

        try {
            app(MessageService::class)->sendMsg($msg, $sendMsgRequest->receiver_ids, $sendMsgRequest->body, $sendMsgRequest->attach_id ?? 0, '', $sendMsgRequest->send_time ?? '');
        } catch (\Throwable $e) {
            LoggerHelper::logSendMessage('发送消息(新)-处理失败' . $e->getMessage());

            return $this->message($e->getMessage(), 0);
        }

        return $this->success();
    }

    /**
     * 批量发送消息给用户
     *
     * @param BatchSendSystemMessagePost $request
     * @return JsonResponse
     */
    public function batchSendSystemMessage(BatchSendSystemMessagePost $request): JsonResponse
    {
        if ($request->message) {
            return $this->message($request->message, 0);
        }

        BatchSendSystemMessage::dispatch($request->list)->onQueue('batch_system_message_queue');
        LoggerHelper::logSystemMessage('批量发送消息(新)-添加批量发送消息队列成功', 'info');
        return $this->success();
    }

    /**
     * 发送系统消息
     * @param Request $request
     * @return JsonResponse
     * @throws \Exception
     */
    public function sendSystemMessage(Request $request): JsonResponse
    {
        $request->validate([
            'receiver_id' => 'required|integer|exists:oc_customer,customer_id',
            'subject' => 'required',
            'body' => 'required',
            'msg_type' => 'required'
        ]);
        $res = Message::addSystemMessage($request->msg_type, $request->subject, $request->body, $request->receiver_id);
        if ($res) {
            return $this->success();
        }
        return $this->failed();
    }

    /**
     * 发送店铺站内信
     *
     * @param Request $request
     * @return JsonResponse
     * @throws \Exception
     */
    public function sendStoreMessage(Request $request): JsonResponse
    {
        $request->validate([
            'send_id' => 'required|integer|exists:oc_customer,customer_id',
            'receiver_id' => 'required|integer|exists:oc_customer,customer_id',
            'title' => 'required',
            'content' => 'required',
        ]);
        $data['title'] = $request->input('title');
        $data['content'] = $request->input('content');
        $data['parent_id'] = $request->input('parent_id', 0);
        $data['attach'] = $request->input('attach', '');
        $data['status'] = $request->input('status', 0);
        $res = Message::addStoreMessage($request->send_id, $request->receiver_id, $data);
        if ($res) {
            return $this->success();
        }
        return $this->failed();
    }

    /**
     * 批量发送店铺站内信
     * @param Request $request
     * @return JsonResponse
     */
    public function batchStoreMessage(Request $request): JsonResponse
    {
        $request->validate([
            'send_id' => 'required|integer|exists:oc_customer,customer_id',
            'receiver_ids' => 'required',
            'subject' => 'required',
            'body' => 'required',
        ]);

        $receiverIds = [];
        if (!is_array($request->input('receiver_ids'))) {
            $receiverIds = explode(',', $request->input('receiver_ids'));
        }
        if (empty($receiverIds)) {
            return $this->failed();
        }

        $msg = new Msg();
        $msg->sender_id = request('send_id');
        $msg->title = request('subject');
        $msg->parent_msg_id = request('subject', 0);
        $msg->status = request('status', 0);
        $msg->is_sent = 1;

        try {
            app(MessageService::class)->sendMsg($msg, $receiverIds, request('body'), 0,  request('attach', ''));
        } catch (\Throwable $e) {
            LoggerHelper::logSendMessage('发送消息处理失败' . $e->getMessage());
            return $this->failed();
        }

        return $this->success();
    }

    /**
     * @param Request $request
     * @param to String|Array 邮件接受者,如果是数组发送给多个人
     * @param subject String 邮件主题
     * @param body String 邮件内容
     * @return JsonResponse|void
     */
    public function sendMail(Request $request)
    {
        $request->validate([
            'to' => 'required',
            'subject' => 'required',
            'body' => 'required'
        ]);
        if (is_array($request->to)) {
            $request->validate(['to.*' => 'email|distinct']);
        } else {
            $request->validate(['to' => 'email']);
        }
        $data = $request->all();
        // html标签处理
        $data['subject'] = str_replace("<b>", " ", $data['subject']);
        $data['subject'] = str_replace("</b>", " ", $data['subject']);
        $data['subject'] = strip_tags($data['subject']);
        if (is_array($data['to'])) {
            foreach ($request->to as $item) {
                $data['to'] = $item;
                SendMail::dispatch($data);
            }
        } else {
            SendMail::dispatch($data);
        }
        return new JsonResponse(true);
    }

    /**
     * 发送通知类站内信邮件
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function sendStationLetter(Request $request)
    {
        $request->validate([
            'letter_id' => 'required',
        ]);
        $res = StationLetter::sendStationLetterEmail($request->input('letter_id'));
        if ($res) {
            return $this->success();
        }
        return $this->failed();
    }

}