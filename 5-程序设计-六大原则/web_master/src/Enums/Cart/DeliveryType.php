<?php

namespace App\Enums\Cart;

use Framework\Enum\BaseEnum;

class DeliveryType extends BaseEnum
{
    const DROP_SHIP = 0; // 默认或最优价加入
    const HOME_PICK = 1; // 常规价加入
    const CLOUD_LOGISTICS = 2; // 阶梯价加入
}
