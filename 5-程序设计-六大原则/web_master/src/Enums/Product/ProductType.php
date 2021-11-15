<?php

namespace App\Enums\Product;

use Framework\Enum\BaseEnum;

class ProductType extends BaseEnum
{
    const NORMAL = 0; // 常规
    const MARGIN_DEPOSIT = 1; // 保证金
    const FUTURE_MARGIN_DEPOSIT = 2; // 期货保证金
    const COMPENSATION_FREIGHT = 3; // 补运费

    /**
     * 定金
     * @return int[]
     */
    public static function deposit()
    {
        return [self::MARGIN_DEPOSIT, self::FUTURE_MARGIN_DEPOSIT];
    }
}
