<?php

namespace App\Enums\Order;

class OcOrderTypeId
{
    const TYPE_PO = 0;
    const TYPE_REBATE = 1;
    const TYPE_MARGIN = 2;
    const TYPE_FUTURE = 3;

    public static function getViewItems()
    {
        return [
            static::TYPE_PO => 'PO',
            static::TYPE_REBATE => 'Rebate order',
            static::TYPE_MARGIN => ['Margin agreement deposit','Margin order'],
            static::TYPE_FUTURE => ['Future goods agreement deposit','Future goods order'],
        ];
    }
}
