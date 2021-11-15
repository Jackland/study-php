<?php

namespace App\Enums\Country;

use Framework\Enum\BaseEnum;

class Country extends BaseEnum
{
    const AMERICAN = 223; // 内部
    const JAPAN = 107; // 外部
    const BRITAIN = 222; // Test
    const GERMANY = 81;


    public static function getViewItems()
    {
        return [
            static::AMERICAN => 'USA',
            static::JAPAN => 'JPN',
            static::BRITAIN => 'GBR',
            static::GERMANY => 'DEU',
        ];
    }

    /**
     * 获取欧洲国家
     *
     * @return int[]
     */
    public static function getEuropeCountries(): array
    {
        return [
            static::BRITAIN,
            static::GERMANY,
        ];
    }
}
