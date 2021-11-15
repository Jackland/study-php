<?php

namespace App\Enums\YzcRmaOrder;

use Framework\Enum\BaseEnum;

class RmaStatus extends BaseEnum
{
    const RMA_STATUS_APPLYIED = 1;
    const RMA_STATUS_PROCESSED = 2;
    const RMA_STATUS_PENDING = 3;
    const RMA_STATUS_CANCELED = 4; //摘自oc_yzc_rma_status
}
