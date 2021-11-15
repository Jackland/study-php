<?php

namespace App\Enums\Future;

use Framework\Enum\BaseEnum;

class FutureMarginContractDeliveryType extends BaseEnum
{
    const FUTURE_UNIT = 1;
    const MARGIN_UNIT = 2;
    const MIX_UNIT = 3;

    public static function getViewItems()
    {
        return [
            self::FUTURE_UNIT => 'Direct Settlement',
            self::MARGIN_UNIT => 'Transfer to Margin Transaction',
            self::MIX_UNIT => 'Direct Settlement/Transfer to Margin Transaction',
        ];
    }

    public static function getIncludeMarginUnit()
    {
        return [self::MARGIN_UNIT, self::MIX_UNIT];
    }
}
