<?php

namespace App\Enums\Customer;

use Framework\Enum\BaseEnum;

class CustomerAccountingType extends BaseEnum
{
    const INNER = 1; // 内部
    const OUTER = 2; // 外部
    const TEST = 3; // Test
    const SERVICE_SHOP = 4;
    const AMERICA_NATIVE = 5;
    const GIGA_ONSIDE = 6; //giga onsite  字母拼错了，忽略

    public static function getViewItems()
    {
        return [
            static::INNER => 'I Accounting',
            static::OUTER => 'O Accounting',
            static::TEST => 'Test Account',
            static::SERVICE_SHOP => 'Service Shop',
            static::AMERICA_NATIVE => 'America Native',
            static::GIGA_ONSIDE => 'Giga Onsite',
        ];
    }

    public static function outerAccount()
    {
        return [
            self::OUTER,
            self::GIGA_ONSIDE
        ];
    }
}
