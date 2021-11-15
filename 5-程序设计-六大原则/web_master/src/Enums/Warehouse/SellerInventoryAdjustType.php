<?php

namespace App\Enums\Warehouse;


use Framework\Enum\BaseEnum;

class SellerInventoryAdjustType extends BaseEnum
{
    const UP = 1; //  库存上调
    const DOWN = 2; //  库存下调
    const PROFIT = 3; // 盘盈
    const LOSE = 4; // 盘亏
}
