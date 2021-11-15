<?php

namespace App\Enums\Stock;

use App\Enums\BaseEnum;

class BuyerProductLockEnum extends BaseEnum
{
    const INVENTORY_REDUCTION = 1;
    const INVENTORY_LOSS = 2;
    const INVENTORY_PRE_ASSOCIATED = 3; // 囤货预绑定
}
