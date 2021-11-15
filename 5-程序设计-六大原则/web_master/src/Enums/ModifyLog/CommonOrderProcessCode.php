<?php

namespace App\Enums\ModifyLog;

use Framework\Enum\BaseEnum;

class CommonOrderProcessCode extends BaseEnum
{
    const CHANGE_ADDRESS = 1;
    const CHANGE_SKU = 2;
    const CANCEL_ORDER = 3;
}
