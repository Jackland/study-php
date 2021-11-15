<?php

use App\Enums\Common\YesNoEnum;
use App\Enums\Message\MsgDeleteStatus;
use App\Enums\Message\MsgReceiveSendType;
use App\Exception\InvalidSendMessageException;
use App\Models\Customer\Customer;
use App\Models\Message\MsgReceive;
use App\Repositories\Message\StatisticsRepository as MessageStatisticsRepositoryAlias;
use App\Services\Message\MessageService;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;

/**
 *
 * 站内信和消息通知模型
 * Class ModelMessageMessage
 * @property ModelMessageMessageSetting model_message_messageSetting
 */

class ModelMessageMessage extends Model
{
    static $page_size = 15;
    const STATION_MSG = 0; //站内信

    const TYPE_DEFAULT = 000;
    const TYPE_PRODUCT = 100;
    const TYPE_RMA = 200;
    const TYPE_BID = 300;
    const TYPE_ORDER = 400;
    const TYPE_OTHER = 500;
    const TYPE_INVOICE = 600;
    const TYPE_RECEIPTS = 700;//入库单

    public static $msgType = [
        'product' => 100,
        'product_stock' => 101,
        'product_review' => 102,
        'product_approve' => 103,
        'product_status' => 104,
        'product_stock-in' => 105,
        'product_price' => 106,
        'product_inventory' => 107,
        'rma' => 200,
        'bid' => 300,
        'bid_rebates' => 301,
        'bid_margin' => 302,
        'bid_futures' => 303,
        'order_status' => 401,
        'sales_order' => 402,
        'purchase_order' => 403,
        'pickup_order'=> 404,
        'invoice' => 600,
        'receipts' => 700,
    ];

    public static $typeSm = [
        '104' => 'Status',
        '105' => 'Stock-In',
        '106' => 'Price',
        '107' => 'Inventory',
        '300' => 'Spot Price',
        '301' => 'Rebates',
        '302' => 'Margin',
        '303' => 'Futures',
        '402' => 'Sales Order',
        '403' => 'Purchase Order',
        '404' => 'Pickup Order',
    ];

    /**
     * 校验sender 和 receiver是否同国别，只有同国别才能发送消息
     * @param int $senderId
     * @param int $receiverId
     * @throws InvalidSendMessageException
     */
    public function checkSendMessageValid(int $senderId, int $receiverId)
    {
        if (!$senderId || !$receiverId) {
            return;
        }
        $sender = Customer::find($senderId);
        $receiver = Customer::find($receiverId);
        if ($sender->country_id == $receiver->country_id) {
            return;
        }
        if ($sender->buyer) {
            throw new InvalidSendMessageException('You are not able to establish contact or message communication with this Seller since you are not in the same Country Market as the Seller.');
        }
        if ($receiver->buyer) {
            throw new InvalidSendMessageException('You are not able to establish contact or message communication with this Buyer since you are not in the same Country Market as the Buyer.');
        }
    }

    /**
     * 获取某个用户收件箱中来自其他用户消息的未读数量
     * @param int $customerId
     * @param array $where
     * @return int
     */
    function unReadMessageCount($customerId, array $where = []): int
    {
        $createTime = '';
        if (isset($where['create_time'])) {
            $createTime = $where['create_time'];
        }

        return MsgReceive::queryRead()
            ->where('send_type', MsgReceiveSendType::USER)
            ->where('receiver_id', $customerId)
            ->where('delete_status', MsgDeleteStatus::NOT_DELETED)
            ->where('is_read', YesNoEnum::NO)
            ->when(!empty($createTime), function ($query) use ($createTime) {
                $query->where('create_time', '>=', $createTime);
            })
            ->count('id');
    }

    /**
     *
     * 获取seller店铺名称
     * @param int $customerId
     * @return \Illuminate\Database\Eloquent\Model|Builder|object|null
     */
    function getCustomerPartnerInfoById($customerId)
    {
        return $this->orm->table(DB_PREFIX . 'customerpartner_to_customer')
            ->select(['customer_id','avatar', 'screenname as username'])
            ->where(['customer_id' => $customerId])
            ->first();
    }

    /**
     *
     * 获取seller店铺名称
     * @param int $customerId
     * @return \Illuminate\Database\Eloquent\Model|Builder|object|null
     */
    function getCustomerInfoById($customerId)
    {
        return $this->orm->table(DB_PREFIX . 'customer')
            ->select(['customer_id', 'nickname as username', 'user_number','email'])
            ->where(['customer_id' => $customerId])
            ->first();
    }

    /**
     * 未读消息 system 消息分类
     * @param int $customerId
     * @return array
     */
    function unReadSystemMessageCount($customerId): array
    {
        $typesCount = app(MessageStatisticsRepositoryAlias::class)->getCustomerInboxFromSystemUnreadMainTypesCount($customerId);
        $typesCount['000'] = array_sum(array_values($typesCount));

        return $typesCount;
    }


    /**
     * 所有的system未读消息
     * @param int $customerId
     * @param array $where
     * @return int
     */
    function unReadAllSystemMessageCount($customerId, array $where = []): int
    {
        $createTime = '';
        if (isset($where['create_time'])) {
            $createTime = $where['create_time'];
        }

        return MsgReceive::queryRead()
            ->where('send_type', MsgReceiveSendType::SYSTEM)
            ->where('receiver_id', $customerId)
            ->where('delete_status', MsgDeleteStatus::NOT_DELETED)
            ->where('is_read', YesNoEnum::NO)
            ->when(!empty($createTime), function ($query) use ($createTime) {
                $query->where('create_time', '>=', $createTime);
            })
            ->count();
    }

    /**
     * 平台客服站内信未读数
     * @param int $customerId
     * @return int
     */
    function unReadTicketCount($customerId, $where = [])
    {
        $where['create_customer_id'] = $customerId;
        $where['customer_is_read'] = 0;
        $create_time = null;
        if (isset($where['create_time'])) {
            $create_time = $where['create_time'];
            unset($where['create_time']);
        }
        return $this->orm->connection('read')->table(DB_PREFIX . 'ticket')
            ->when($create_time, function ($query) use ($create_time) {
                $query->where('date_added', '>=', $create_time);
            })
            ->where($where)
            ->count();
    }


    /**
     * 插入系统消息 to seller
     * @param $msgTypeKey
     * @param $orgData
     * @param string $receiverId
     * @return bool|string
     */
    function addSystemMessage($msgTypeKey, $orgData, $receiverId = '')
    {
        if (!isset(self::$msgType[$msgTypeKey])) {
            return false;
        }

        $receiverArr = [];
        if (!empty($receiverId) && is_numeric($receiverId)) {
            $receiverArr = [$receiverId];
        } elseif (isset($orgData['order_id'])) {
            $receiverArr = $this->getSellersByOrderId($orgData['order_id']);
        } elseif (isset($orgData['product_id'])) {
            $receiverId = $this->getSellerIdByProductId($orgData['product_id']);
            if ($receiverId) {
                $receiverArr = [$receiverId];
            }
        }
        if (empty($receiverArr)) {
            return false;
        }

        try {
            app(MessageService::class)->buildMsg(0, $msgTypeKey, json_encode($orgData), [], $receiverArr, self::$msgType[$msgTypeKey]);
        } catch (Throwable $e) {
            return $e->getMessage();
        }
        return true;
    }


    /*
     * 插入系统消息 to buyer
     * 初版时 发送给seller的system消息是json结构，发送给buyer的system消息是html文本
     * 故此方式不仅限发给buyer，发送给seller亦可用
     * by chenyang 附加理解此方法的功能意义。此方法不仅仅是只用于发送系统消息给buyer，可以用作发消息给任意buyer/seller角色。
     * 和上文的addSystemMessage方法相比，此方法保存内容没有做json转换。
     * 所以，此方法可以用作发送编辑好的HTML内容消息，在界面显示时，也无需识别json参数。
     *
     * */
    function addSystemMessageToBuyer($msgTypeKey, $subject, $message, $receiverId, $senderId = 0)
    {
        if (!isset(self::$msgType[$msgTypeKey])) {
            return false;
        }

        $receiverArr = Arr::wrap($receiverId);
        if (empty($receiverArr)) {
            return false;
        }

        // 插入数据
        try {
            app(MessageService::class)->buildMsg($senderId, $subject, $message, [], $receiverArr, self::$msgType[$msgTypeKey]);
        } catch (Throwable $e) {
            return $e->getMessage();
        }
        return true;
    }

    /*
     * 获取订单相关所有seller的ID
     * */
    public function getSellersByOrderId($orderId)
    {
        return $this->orm->table(DB_PREFIX . 'customerpartner_to_order')
            ->where('order_id', $orderId)
            ->groupBY('customer_id')
            ->pluck('customer_id')
            ->toArray();

    }

    /*
     * 获取productId对应sellerId
     * */
    public function getSellerIdByProductId($productId)
    {
        return $this->orm->table(DB_PREFIX . 'customerpartner_to_product')
            ->where('product_id', $productId)
            ->value('customer_id');
    }
}
