<?php

namespace App\Enums\YzcRmaOrder;

use Framework\Enum\BaseEnum;

class RmaRefundStatus extends BaseEnum
{
    const PRODUCT_RMA_ORIGIN = 0;
    const PRODCUT_RMA_AGREED = 1;
    const PRODUCT_RMA_REJECTED = 2;
}
