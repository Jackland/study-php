<?php

namespace App\Enums\Marketing;

use Framework\Enum\BaseEnum;

class MarketingDiscountProductType extends BaseEnum
{
    const SCOPE_ALL = -1; //所有商品(目前只有这一种)

    public static function getViewItems()
    {
        return [
            static::SCOPE_ALL => 'All',
        ];
    }

}
