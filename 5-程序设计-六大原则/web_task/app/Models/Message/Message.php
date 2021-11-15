<?php
/**
 * Created by PhpStorm.
 * User: Lu.Chen
 * Date: 2019/11/18
 * Time: 14:40
 */

namespace App\Models\Message;

use App\Enums\Message\MsgMsgType;
use App\Helpers\LoggerHelper;
use App\Jobs\SendMail;
use App\Models\Customer\Customer;
use App\Services\File\Tool\FileDeal;
use App\Services\Message\MessageService;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $table = 'oc_message';
    protected $connection = 'mysql_proxy';

    const STATION_MSG = 0; // 店铺站内信

    public static $msgType = [
        'product' => 100,
        'product_stock' => 101,
        'product_review' => 102,
        'product_approve' => 103,
        'product_status' => 104,
        'product_stock-in' => 105,
        'product_price' => 106,
        'product_inventory' => 107,
        'product_subscribe' => 109,
        'rma' => 200,
        'bid' => 300,
        'bid_rebates' => 301,
        'bid_margin' => 302,
        'bid_futures'=>303,
        'order_status' => 401,
        'sales_order' => 402,
        'purchase_order' => 403,
        'pickup_order'=> 404,
        'other' => 500,
        'invoice' => 600,
        'receipts' => 700,
    ];


    /**
     * 发送系统消息 html格式
     * @param $msgTypeKey
     * @param $subject
     * @param $content
     * @param $receiverId
     * @return bool
     */
    public static function addSystemMessage($msgTypeKey, $subject, $content, $receiverId): bool
    {
        if (! in_array($msgTypeKey, MsgMsgType::getAllTypeKeys())) {
            return false;
        }

        $msg = new Msg();
        $msg->sender_id = 0;
        $msg->title = $subject;
        $msg->msg_type = MsgMsgType::getTypeValue($msgTypeKey);
        $msg->is_sent = 1;

        try {
            app(MessageService::class)->sendMsg($msg, $receiverId, $content);
        } catch (\Throwable $e) {
            LoggerHelper::logSystemMessage('发送系统消息异常：' . $e->getMessage(), 'error');
            return false;
        }

        return true;
    }

    /**
     * 发送站内信消息
     *
     * @param $sendId
     * @param int|array $receiverId 接受者ID
     * @param $data
     * @return bool
     */
    public static function addStoreMessage($sendId, $receiverId, $data): bool
    {
        $msgType = $data['msg_type'] ?? 0;
        $content = html_entity_decode(trim($data['content']), ENT_QUOTES, 'UTF-8');

        $msg = new Msg();
        $msg->sender_id = $sendId;
        $msg->title = $data['title'];
        $msg->msg_type = $msgType;
        $msg->parent_msg_id = $data['parent_id'] ?? 0;
        $msg->status = $data['status'] ?? 0;
        $msg->is_sent = 1;

        try {
            app(MessageService::class)->sendMsg($msg, $receiverId, $content, 0, $data['attach'] ?? '');
        } catch (\Throwable $e) {
            LoggerHelper::logSendMessage('发送消息处理失败' . $e->getMessage());
            return false;
        }

        return true;
    }


    /*
     * 用户昵称(用户编号)
     * */
    public function getNickNameNumber($customerId)
    {
        $info = \DB::connection('mysql_proxy')
            ->table('oc_customer')
            ->where('customer_id', $customerId)
            ->select('nickname', 'user_number')
            ->first();

        return empty($info) ? '' : $info->nickname . '(' . $info->user_number . ')';
    }

    /**
     * @param int $senderId
     * @return string
     */
    public static function mailFrom(int $senderId): string
    {
        if (in_array($senderId, config('app.gigacloud_platfrom_buyer'))) {
            return '[From GIGACLOUD]';
        }

        if ($senderId == MessageService::SYSTEM_ID) {
            return '[From GIGACLOUD]';
        }

        if ($senderId == MessageService::PLATFORM_SECRETARY) {
            return '[From GIGACLOUD]';
        }

        if (Customer::isPartner($senderId)) {
            return '[From Buyer]';
        } else {
            return '[From Seller]';
        }
    }

}