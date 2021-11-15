<?php

namespace App\Enums\Message;

use App\Enums\BaseEnum;

class MsgType extends BaseEnum
{
    const NORMAL = 0;
    const PRODUCT = 100;
    const PRODUCT_STOCK = 101;
    const PRODUCT_REVIEW = 102;
    const PRODUCT_APPROVE = 103;
    const PRODUCT_STATUS = 104;
    const PRODUCT_STOCK_IN = 105;
    const PRODUCT_PRICE = 106;
    const PRODUCT_INVENTORY = 107;
    const RMA = 200;
    const BID = 300;
    const BID_REBATES = 301;
    const BID_MARGIN = 302;
    const BID_FUTURES = 303;
    const ORDER = 400;
    const ORDER_STATUS = 401;
    const SALES_ORDER = 402;
    const PURCHASE_ORDER = 403;
    const PICKUP_ORDER = 404;
    const OTHER = 500;
    const INVOICE = 600;
    const INCOMING_SHIPMENT = 700;

    /**
     * @return int[]
     */
    public static function mainTypeMap(): array
    {
        return [
            self::PRODUCT => [
                self::PRODUCT,
                self::PRODUCT_STOCK,
                self::PRODUCT_REVIEW,
                self::PRODUCT_APPROVE,
                self::PRODUCT_STATUS,
                self::PRODUCT_STOCK_IN,
                self::PRODUCT_PRICE,
                self::PRODUCT_INVENTORY,
            ],
            self::RMA => [
                self::RMA
            ],
            self::BID => [
                self::BID,
                self::BID_REBATES,
                self::BID_MARGIN,
                self::BID_FUTURES,
            ],
            self::ORDER => [
                self::ORDER,
                self::ORDER_STATUS,
                self::SALES_ORDER,
                self::PURCHASE_ORDER,
                self::PICKUP_ORDER,
            ],
            self::OTHER => [
                self::OTHER
            ],
            self::INVOICE => [
                self::INVOICE
            ],
            self::INCOMING_SHIPMENT => [
                self::INCOMING_SHIPMENT
            ],
        ];
    }

    /**
     * @param int|null $msgType
     * @return string
     */
    public static function getMsgMainTypeName(int $msgType): string
    {
        switch ($msgType) {
            case $msgType >= self::PRODUCT && $msgType < self::RMA;
                return 'Product';
            case $msgType >= self::RMA && $msgType < self::BID;
                return 'RMA';
            case $msgType >= self::BID && $msgType < self::ORDER;
                return 'Bid';
            case $msgType >= self::ORDER && $msgType < self::OTHER;
                return 'Order';
            case $msgType >= self::OTHER && $msgType < self::INVOICE;
                return 'Other';
            case $msgType >= self::INVOICE && $msgType < self::INCOMING_SHIPMENT;
                return 'Invoice';
            case $msgType >= self::INCOMING_SHIPMENT && $msgType < 800;
                return 'Incoming Shipment';
        }

        return '';
    }

    /**
     * @return array|string[]
     */
    public static function getViewItems(): array
    {
        return [
            self::NORMAL => 'Normal',
            self::PRODUCT => 'Product',
            self::PRODUCT_STOCK => 'Product Stock',
            self::PRODUCT_REVIEW => 'Product Review',
            self::PRODUCT_APPROVE => 'Product Approve',
            self::PRODUCT_STATUS => 'Product Status',
            self::PRODUCT_STOCK_IN => 'Product Stock-in',
            self::PRODUCT_PRICE => 'Product Price',
            self::PRODUCT_INVENTORY => 'Product Inventory',
            self::RMA => 'RMA',
            self::BID => 'Spot Price',
            self::BID_REBATES => 'Rebates',
            self::BID_MARGIN => 'Margin',
            self::BID_FUTURES => 'Futures',
            self::ORDER => 'Order',
            self::ORDER_STATUS => 'Order Status',
            self::SALES_ORDER => 'Sales Order',
            self::PURCHASE_ORDER => 'Purchase Order',
            self::PICKUP_ORDER => 'Pickup Order',
            self::OTHER => 'Other',
            self::INVOICE => 'Invoice',
            self::INCOMING_SHIPMENT => 'Incoming Shipment',
        ];
    }
}
