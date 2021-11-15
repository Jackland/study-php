<?php

namespace App\Services\Message;

use App\Components\BatchInsert;
use App\Components\RemoteApi;
use App\Components\RemoteApi\B2BManager\Enums\FileResourceTypeEnum;
use App\Enums\Message\MsgMode;
use App\Enums\Message\MsgReceiveSendType;
use App\Enums\Message\MsgReceiveType;
use App\Enums\Message\MsgType;
use App\Listeners\Events\SendMsgMailEvent;
use App\Models\Customer\Customer;
use App\Models\Message\Msg;
use App\Models\Message\MsgContent;
use App\Models\Message\MsgReceive;
use App\Models\Rma\YzcRmaOrder;
use App\Repositories\Customer\CustomerRepository;

class MessageService
{
    /**
     * 消息标题替换
     * @param string $title
     * @param int $msgType
     * @param string $content
     * @return string
     */
    public function replaceMsgTitle(string $title, int $msgType, string $content = ''): string
    {
        if ($msgType == MsgType::NORMAL) {
            return $title;
        }

        $jsonContent = json_decode($content, true);

        switch ($msgType) {
            case $msgType >= MsgType::PRODUCT && $msgType < MsgType::RMA:
                if ($jsonContent) {
                    return $this->formatProductMsgJsonContent($jsonContent, $msgType, 'title');
                }
                break;
            case $msgType >= MsgType::RMA && $msgType < MsgType::BID:
                if ($jsonContent) {
                    return $this->formatRMAMsgJsonContent($jsonContent, 'title');
                }
                if (substr($title, 0, strlen('RMA Processed Result')) == 'RMA Processed Result') {
                    return 'Purchase Order RMA Status Outcome' . substr($title, strlen('RMA Processed Result'));
                } elseif (substr($title, 0, strlen('Purchase Order RMA Processed Result')) == 'Purchase Order RMA Processed Result') {
                    return 'Purchase Order RMA Status Outcome' . substr($title, strlen('Purchase Order RMA Processed Result'));
                }
                break;
            case $msgType >= MsgType::BID && $msgType < MsgType::ORDER:
                if ($jsonContent) {
                    return $this->formatBidMsgJsonContent($jsonContent, $msgType, 'title');
                }
                break;
            case $msgType >= MsgType::ORDER && $msgType < MsgType::OTHER:
                if ($jsonContent) {
                    return $this->formatOrderMsgJsonContent($jsonContent, $msgType, 'title');
                }
                if (substr($title, 0, strlen('The purchase order')) == 'The purchase order') {
                    return 'P' . substr($title, 5);
                }
                break;
        }

        return $title;
    }

    /**
     * 消息内容替换
     * @param string $content
     * @param int $msgType
     * @return string
     */
    public function replaceMsgContent(string $content, int $msgType): string
    {
        if ($msgType == MsgType::NORMAL) {
            return $content;
        }

        $jsonContent = json_decode($content, true);

        switch ($msgType) {
            case $msgType >= MsgType::PRODUCT && $msgType < MsgType::RMA:
                if ($jsonContent) {
                    return $this->formatProductMsgJsonContent($jsonContent, $msgType);
                }
                break;
            case $msgType >= MsgType::RMA && $msgType < MsgType::BID:
                if ($jsonContent) {
                    return $this->formatRMAMsgJsonContent($jsonContent);
                }
                if (strstr($content, 'Refund Processed Result：') !== false) {
                    return str_replace('Refund Processed Result：', 'Refund Request Outcome: ', $content);
                }
                break;
            case $msgType >= MsgType::BID && $msgType < MsgType::ORDER:
                if ($jsonContent) {
                    return $this->formatBidMsgJsonContent($jsonContent, $msgType);
                }
                break;
            case $msgType >= MsgType::ORDER && $msgType < MsgType::OTHER:
                if ($jsonContent) {
                    return $this->formatOrderMsgJsonContent($jsonContent, $msgType);
                }
                break;
        }

        return $content;
    }

    /**
     * @param int $senderId
     * @param string $subject
     * @param string $content
     * @param array $files
     * @param array $receiverIds
     * @param int $msgType
     * @param int $parentMsgId
     * @return mixed
     * @throws \Throwable
     */
    public function buildMsg(int $senderId, string $subject, string $content, array $files, array $receiverIds, int $msgType = MsgType::NORMAL, int $parentMsgId = 0)
    {
        $fileList = null;
        if (!empty($files) && $senderId > 0) {
            $isSeller = app(CustomerRepository::class)->checkIsSeller($senderId);
            $fileList = RemoteApi::file()->upload($isSeller ? FileResourceTypeEnum::MESSAGE_SELLER : FileResourceTypeEnum::MESSAGE_BUYER, $files);
        }

        $parentMsg = null;
        if (!empty($parentMsgId)) {
            $parentMsg = Msg::query()->find($parentMsgId);
        }

        $msgId = dbTransaction(function () use ($senderId, $subject, $content, $fileList, $receiverIds, $msgType, $parentMsg) {
            $attachId = 0;
            if (!is_null($fileList)) {
                RemoteApi::file()->confirmUpload($fileList->menuId, $fileList->list->pluck('subId')->toArray());
                $attachId = $fileList->menuId;
            }

            // 1.创建消息
            $msgData = [
                'sender_id' => $senderId,
                'title' => trim($subject),
                'msg_type' => $msgType,
                'receive_type' => (count($receiverIds) == 1 && $receiverIds[0] == Msg::PLATFORM_SECRETARY_SENDER_OR_RECEIVER_ID) ? MsgReceiveType::PLATFORM_SECRETARY : MsgReceiveType::USER,
                'parent_msg_id' => is_null($parentMsg) ? 0 : $parentMsg->id,
                'msg_mode' => count($receiverIds) > 1 ? MsgMode::MASS : MsgMode::PRIVATE_CHAT,
            ];
            if ($parentMsg instanceof Msg) {
                $msgData['root_msg_id'] = $parentMsg->root_msg_id;
                $msgId = Msg::query()->insertGetId($msgData);
            } else {
                $msgId = Msg::query()->insertGetId($msgData);
                Msg::query()->where('id', $msgId)->update(['root_msg_id' => $msgId]);
            }

            // 2.保存内容
            MsgContent::query()->insert([
                'msg_id' => $msgId,
                'content' => trim($content),
                'attach_id' => $attachId,
            ]);

            // 3.保存接受者
            $batchInsert = new BatchInsert();
            $batchInsert->begin(MsgReceive::class);
            $sendType = MsgReceiveSendType::getSenderType($senderId);
            foreach ($receiverIds as $receiverId) {
                $batchInsert->addRow([
                    'msg_id' => $msgId,
                    'receiver_id' => $receiverId,
                    'send_type' => $sendType,
                ]);
            }
            $batchInsert->end();

            return $msgId;
        });

        event(new SendMsgMailEvent($msgId, $receiverIds));

        return $msgId;
    }

    /**
     * @param array $content
     * @param int $msgType
     * @param string $formatType content|title
     * @return string
     */
    private function formatProductMsgJsonContent(array $content, int $msgType, string $formatType = 'content'): string
    {
        $author = !empty($content['review_id']) ? $this->getCustomerName($content['review_id']) : '';
        $id = $content['id'] ?? '';
        $productId = $content['product_id'] ?? '';
        $productName = $content['product_name'] ?? '';

        switch ($msgType) {
            case MsgType::PRODUCT_REVIEW:
                if ($formatType == 'content') {
                    return sprintf('New review: #%s has been placed by <b>%s</b> For product <a href="index.php?route=product/product&product_id=%s" target="_blank">#%s</a> <br/>', $id, $author, $productId, $productName);
                } else {
                    return sprintf('New review: #%s has been placed by <b>%s</b> For product %s<br/>', $id, $author, truncate($productName, 60));
                }
            case MsgType::PRODUCT_STOCK:
                if ($formatType == 'content') {
                    return sprintf('<a href="index.php?route=product/product&product_id=%s" target="_blank" title="%s">%s</a> is out of stock;', $id, $productName, $productName);
                } else {
                    return sprintf('<b>%s</b> is out of stock;', truncate($productName, 60));
                }
            case MsgType::PRODUCT_APPROVE:
                if ($formatType == 'content') {
                    return sprintf('<a href="index.php?route=product/product&product_id=%s" target="_blank"><b>%s</b></a> has been approved;', $id, $productName);
                } else {
                    return sprintf('<b>%s</b> has been approved;', truncate($productName, 60));
                }
        }

        return '';
    }

    /**
     * @param array $content
     * @param string $formatType
     * @return string
     */
    private function formatRMAMsgJsonContent(array $content, string $formatType = 'content'): string
    {
        $rmaOrderId = 'Unknown';
        $author = !empty($content['buyer_id']) ? $this->getCustomerName($content['buyer_id']) : '';
        $rmaId = $content['rma_id'] ?? '';
        if (!empty($rmaId)) {
            /** @var YzcRmaOrder $rmaOrder */
            $rmaOrder = YzcRmaOrder::queryRead()->where('id', $rmaId)->first();
            $rmaOrderId = $rmaOrder->rma_order_id;
        }

        if ($formatType == 'content') {
            return sprintf('<b>New Application for RMA:</b><a href="%s" target="_blank">#%s</a> has been placed by <b>%s</b>', url(['account/customerpartner/rma_management/rmaInfo', 'rmaId' => $rmaId]), $rmaOrderId, $author);
        } else {
            return sprintf('<b>New Application for RMA:</b>#%s has been placed by <b>%s</b>', $rmaOrderId, $author);
        }
    }

    /**
     * @param array $content
     * @param int $msgType
     * @param string $formatType
     * @return string
     */
    private function formatBidMsgJsonContent(array $content, int $msgType, string $formatType = 'content'): string
    {
        $author = !empty($content['buyer_id']) ? $this->getCustomerName($content['buyer_id']) : '';
        $sku = $content['sku'] ?? '';
        $productId = $content['product_id'] ?? '';
        $agreementId = $content['agreement_id'] ?? '';

        switch ($msgType) {
            case MsgType::BID_REBATES:
                if ($formatType == 'content') {
                    return sprintf('<b>%s</b> has submitted a BID request to <a href="%s" target="_blank">%s</a>: <a href="%s">#%s</a>', $author, url(['product/product', 'product_id' => $productId]), $sku, url(['account/product_quotes/rebates_contract/view', 'contract_id' => $agreementId]), $agreementId);
                } else {
                    return sprintf('<b>%s</b> has submitted a BID request to %s: #%s', $author, $sku, $agreementId);
                }
        }

        return '';
    }

    /**
     * @param array $content
     * @param int $msgType
     * @param string $formatType
     * @return string
     */
    private function formatOrderMsgJsonContent(array $content, int $msgType, string $formatType = 'content'): string
    {
        $author = !empty($content['customer_id']) ? $this->getCustomerName($content['customer_id']) : '';
        $orderId = $content['order_id'] ?? '';

        switch ($msgType) {
            case MsgType::ORDER_STATUS:
                if ($formatType == 'content') {
                    return sprintf('New order: <a href="index.php?route=account/customerpartner/orderinfo&order_id=%s" target="_blank">#%s</a> has been placed by <b>%s</b>', $orderId . '&is_mp=1', $orderId, $author);
                } else {
                    return sprintf('New order: #%s has been placed by <b>%s</b>', $orderId, $author);
                }
        }

        return '';
    }

    /**
     * @param int $customerId
     * @return string
     */
    private function getCustomerName(int $customerId): string
    {
        /** @var Customer $customer */
        $customer = Customer::queryRead()->where('customer_id', $customerId)->first();
        return $customer ? $customer->nickname . '(' . $customer->user_number . ')' : '';
    }
}
