<?php

namespace App\Enums\SalesOrder;

use Framework\Enum\BaseEnum;

class JapanSalesOrder extends BaseEnum
{
    const SHIP_DATE_REGEX = '/^[1-9]\d{3}(\/|-)(0?[1-9]|1[0-2])(\/|-)(0?[1-9]|[1-2][0-9]|3[0-1])(T08:00-12:00|T12:00-14:00|T14:00-16:00|T16:00-18:00|T18:00-20:00)$/';

    /**
     * 获取指定的时间组
     * @return string[]
     */
    public static function getShipDateList(): array
    {
        return [
            'T08:00-12:00',
            'T12:00-14:00',
            'T14:00-16:00',
            'T16:00-18:00',
            'T18:00-20:00',
        ];
    }
}
