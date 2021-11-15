<?php

namespace App\Enums\Stock;

use Framework\Enum\BaseEnum;

class BuyerProductLockEnum extends BaseEnum
{
    const INVENTORY_REDUCTION = 1;
    const INVENTORY_LOSS = 2;
    const INVENTORY_PRE_ASSOCIATED = 3; // 囤货预绑定

    public static function getViewItems()
    {
        return [
            static::INVENTORY_REDUCTION => 'Inventory Reduction',
            static::INVENTORY_LOSS => 'Inventory Loss',
            static::INVENTORY_PRE_ASSOCIATED => 'Pre-locked',
        ];
    }
}
