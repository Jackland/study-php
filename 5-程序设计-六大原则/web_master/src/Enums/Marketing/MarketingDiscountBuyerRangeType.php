<?php

namespace App\Enums\Marketing;

use Framework\Enum\BaseEnum;

class MarketingDiscountBuyerRangeType extends BaseEnum
{
    const SCOPE_ALL = -1; //所有buyer
    const SCOPE_SOME = 1; //部分buyer

    public static function getViewItems()
    {
        return [
            static::SCOPE_ALL => 'All Buyers',
            static::SCOPE_SOME => 'Specific Buyers',
        ];
    }
}
