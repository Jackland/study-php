<?php

namespace App\Enums\Country;

use App\Enums\BaseEnum;

class Country extends BaseEnum
{
    const AMERICAN = 223;
    const JAPAN = 107;
    const BRITAIN = 222;
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
}
