<?php

namespace App\Enums\Message;

use App\Enums\BaseEnum;

/**
 * oc_msg -> msg_type
 * 消息类型
 *
 * Class MsgMsgType
 * @package App\Enums\Message
 */
class MsgMsgType extends BaseEnum
{
    const DEFAULT_OTHER = 0; // 默认
    const PRODUCT = 100;
    const PRODUCT_STOCK = 101;
    const PRODUCT_REVIEW = 102;
    const PRODUCT_APPROVE = 103;
    const PRODUCT_STATUS = 104;
    const PRODUCT_STOCK_IN = 105;
    const PRODUCT_PRICE = 106;
    const PRODUCT_INVENTORY = 107;
    const PRODUCT_SUBSCRIBE = 109;
    const RMA = 200;
    const BID = 300;
    const BID_REBATES = 301;
    const BID_MARGIN = 302;
    const BID_FUTURES = 303;
    const ORDER_STATUS = 401;
    const SALES_ORDER = 402;
    const PURCHASE_ORDER = 403;
    const PICKUP_ORDER = 404;
    const OTHER = 500;
    const INVOICE = 600;
    const RECEIPTS = 700;

    /**
     * 请求类型和存储值映射
     *
     * @return array
     */
    private static function getAllTypeValue()
    {
        return [
            'default_other' => self::DEFAULT_OTHER,
            'product' => self::PRODUCT,
            'product_stock' => self::PRODUCT_STOCK,
            'product_review' => self::PRODUCT_REVIEW,
            'product_approve' => self::PRODUCT_APPROVE,
            'product_status' => self::PRODUCT_STATUS,
            'product_stock-in' => self::PRODUCT_STOCK_IN,
            'product_price' => self::PRODUCT_PRICE,
            'product_inventory' => self::PRODUCT_INVENTORY,
            'product_subscribe' => self::PRODUCT_SUBSCRIBE,
            'rma' => self::RMA,
            'bid' => self::BID,
            'bid_rebates' => self::BID_REBATES,
            'bid_margin' => self::BID_MARGIN,
            'bid_futures'=> self::BID_FUTURES,
            'order_status' => self::ORDER_STATUS,
            'sales_order' => self::SALES_ORDER,
            'purchase_order' => self::PURCHASE_ORDER,
            'pickup_order'=> self::PICKUP_ORDER,
            'other' => self::OTHER,
            'invoice' => self::INVOICE,
            'receipts' => self::RECEIPTS
        ];
    }

    /**
     * 获取所有的请求类型值
     *
     * @return array
     */
    public static function getAllTypeKeys()
    {
        return array_keys(self::getAllTypeValue());
    }

    /**
     * 获取请求类型对应存储值
     *
     * @param string $typeKey 类型(sales_order)
     * @param int $default 没有获取到取默认值
     * @return int
     */
    public static function getTypeValue($typeKey, $default = 0)
    {
        $types = self::getAllTypeValue();

        $value = isset($types[$typeKey]) ? $types[$typeKey] : $default;
        return $value;
    }

    /**
     * 通过类型值获取对应KEY
     *
     * @param string $typeValue
     * @param string $default
     * @return int
     */
    public static function getTypeKeyByValue($typeValue, $default = 'default_other')
    {
        $types = array_flip(self::getAllTypeValue());

        $typeKey = isset($types[$typeValue]) ? $types[$typeValue] : $default;
        return $typeKey;
    }
}
