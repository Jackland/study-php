<?php

namespace App\Enums\Stock;

use Framework\Enum\BaseEnum;

//tb_sys_receipts_order.shipping_way
class ReceiptsOrderShippingWay extends BaseEnum
{
    const ENTRUSTED_SHIPPING = 1; //委托海运
    const SELLER_SPONTANEOUS_SHIPPING = 2; //seller自发海运
    const B2B_LOCAL = 3; //B2B Local
}
