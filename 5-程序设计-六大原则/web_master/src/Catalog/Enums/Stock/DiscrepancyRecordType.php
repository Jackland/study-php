<?php

namespace App\Catalog\Enums\Stock;

use Framework\Enum\BaseEnum;

/**
 * 入出库流水类型
 *
 * Class DiscrepancyRecordType
 * @package App\Enums\Stock
 */
class DiscrepancyRecordType extends BaseEnum
{
    const RECEIVING = 1; // 入库
    const ALLOCATION = 2; // 出库
    const BLOCKED = 3; // 锁定

    public static function getViewItems()
    {
        return [
            static::RECEIVING => 'Received',
            static::ALLOCATION => 'Allocated',
            static::BLOCKED => 'Blocked',
        ];
    }
}
