<?php

namespace App\Enums\Common;

class AvailableEnum extends YesNoEnum
{
    public static function getViewItems()
    {
        return [
            self::YES => 'Available',
            self::NO => 'Unavailable',
        ];
    }
}
