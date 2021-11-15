<?php

namespace App\Enums\SalesOrder;

use Framework\Enum\BaseEnum;

class JoyBuyOrderStatus extends BaseEnum
{
    const ORDER_STATUS_WAIT_PAY = 1;
    const ORDER_STATUS_PROCESS = 4;
    const ORDER_STATUS_IN_TRANSIT = 5;
    const ORDER_STATUS_COMPLETED = 6;
    const ORDER_STATUS_CANCELED = 99;
}
