<?php

namespace App\Enums\Warehouse;

use Framework\Enum\BaseEnum;

/**
 * 入库单托书贸易方式
 * tb_sys_receipts_order_shipping_order_book.terms_of_delivery
 *
 * Class ShippingOrderBookTermsOfDelivery
 * @package App\Enums\Warehouse
 */
class ShippingOrderBookTermsOfDelivery extends BaseEnum
{
    const FOB = 1;
    const DDP = 2;

    public static function getViewItems()
    {
        return [
            self::FOB => 'FOB',
            self::DDP => 'DDP'
        ];

    }
}
