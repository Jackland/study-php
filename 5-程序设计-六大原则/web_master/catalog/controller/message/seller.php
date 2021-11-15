<?php

use App\Catalog\Controllers\BaseController;
use App\Components\Locker;
use App\Components\Storage\StorageCloud;
use App\Logging\Logger;
use App\Services\Message\MessageService;
use Framework\Action\Action;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * @property ModelMessageMessage $model_message_message
 * @property ModelExtensionModuleWkcontact $model_extension_module_wk_contact
 * @property ModelAccountCustomer $model_account_customer
 */
class ControllerMessageSeller extends BaseController
{
    public function __construct(Registry $registry)
    {
        parent::__construct($registry);

        // 额外放行
        if (!$this->customer->isLogged() && strcmp(request('route'), 'message/seller/download') !== 0 && strcmp(request('route'), 'message/seller/sendMessageBatch') !== 0) {
            $this->url->remember();
            $this->redirect(['account/login'])->send();
        }
    }

    public function index()
    {
        if (customer()->isPartner()) {
            return $this->redirect('customerpartner/message_center/my_message/buyers');
        } else {
            return $this->redirect('account/message_center/seller');
        }
    }

    /**
     * 切换到新版创建消息
     * @return RedirectResponse
     */
    public function addMessage()
    {
        if (customer()->isPartner()) {
            $redirect = ['customerpartner/message_center/message/new'];
        } else {
            $redirect = ['account/message_center/message/new'];
        }
        if (!empty(request('receiver_id', ''))) {
            $redirect['receiver_ids'] = request('receiver_id');
        }
        if (!empty(request('item_code', ''))) {
            // 规定 subject 为： item code:
            $redirect['item_code'] = request('item_code');
        }

        return $this->redirect($redirect);
    }

    /**
     * 批量发送站内信 （现java添加用户未处理销售订单有个站内信提醒，还有一个是批次库存同步插入B2B的站内信提醒）、
     * 现6774 改写此接口
     */
    public function sendMessageBatch()
    {
        $messages = json_decode(html_entity_decode(request('messages')), true);
        $apiToken = request('api_token');

        if ($this->request->server('REQUEST_METHOD') != 'POST' || $apiToken != '760F256C59315F148A43E5644B5B4A18') {
            $result['error'] = 'request type is not post or token is incorrect.';
            return $this->response->json($result);
        }

        $errors = [];
        $errorReceiveIds = [];
        foreach ($messages as $message) {
            // 验证
            if (empty($message)) {
                $errors[] = 'Warning: The attachment is too big.';
                continue;
            }
            if (!isset($message['sendId'])) {
                $errors['error'][] = 'Warning: Something went wrong.';
                $errorReceiveIds[] = $message['receiverId'] ?? 0;
                continue;
            }
            if (!isset($message['receiverId']) || !$message['receiverId']) {
                $errors['error'][] = 'Warning: Something went wrong.';
                $errorReceiveIds[] = $message['receiverId'] ?? 0;
                continue;
            }
            if (!isset($message['subject']) || !$message['subject']) {
                $errors['error'][] = 'Warning: Message subject can not be left blank.';
                $errorReceiveIds[] = $message['receiverId'] ?? 0;
                continue;
            }
            if (!isset($message['content']) || !trim($message['content'])) {
                $errors['error'][] = 'Warning: Message content can not be left blank.';
                $errorReceiveIds[] = $message['receiverId'] ?? 0;
                continue;
            }

            try {
                app(MessageService::class)->buildMsg($message['sendId'], $message['subject'], $message['content'], [], [$message['receiverId']],  $message['messageType']);
            } catch (Exception $e) {
                Logger::error('批量保存站内信发生异常：' . $e);
                $errorReceiveIds[] = $message['receiverId'];
            }
        }

        if (empty($errors) && empty($errorReceiveIds)) {
            $result['success'] = 'he message is finished successfully.';
            return $this->response->json($result);
        }

        return $this->response->json(['error' => $errors, 'error_receive_id' => $errorReceiveIds]);
    }

    /**
     * 保留使用
     * 下载附件
     */
    public function download()
    {
        //处理文件名包含&这种极端情况
        $filename = htmlspecialchars_decode($this->request->get['filename']);
        $maskname = htmlspecialchars_decode($this->request->get['maskname']);
        if ($filename && $maskname) {
            $file = 'download/attachment/' . $filename;
            $mask = basename($maskname);
            $filename_new = $mask ?: $filename;
            if (!headers_sent()) {
                if (StorageCloud::storage()->fileExists($file)) {
                    return StorageCloud::storage()->browserDownload($file, $filename_new);
                } elseif (file_exists(DIR_STORAGE . $file)) {
                    return $this->response->download(DIR_STORAGE . $file,$filename_new);
                } else {
                    return new Action('error/not_found');
                }
            } else {
                exit('Error: Headers already sent out!');
            }
        } else {
            new Action('error/not_found');
        }
    }
}
