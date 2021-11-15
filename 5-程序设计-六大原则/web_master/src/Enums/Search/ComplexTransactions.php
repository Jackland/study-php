<?php

namespace App\Enums\Search;

use Framework\Enum\BaseEnum;

class ComplexTransactions extends BaseEnum
{
    const REBATE = 1;
    const MARGIN = 2;
    const FUTURE = 3;
    const REBATE_MARGIN = 4;
    const REBATE_FUTURE = 5;
    const MARGIN_FUTURE = 6;
    const REBATE_MARGIN_FUTURE = 7;

    public static function getViewItems()
    {
        return [
            static::REBATE => 'REBATE',
            static::MARGIN => 'MARGIN',
            static::FUTURE => 'FUTURE',
            static::REBATE_MARGIN => 'REBATE_MARGIN',
            static::REBATE_FUTURE => 'REBATE_FUTURE',
            static::MARGIN_FUTURE => 'MARGIN_FUTURE',
            static::REBATE_MARGIN_FUTURE => 'REBATE_MARGIN_FUTURE',
        ];
    }
}
