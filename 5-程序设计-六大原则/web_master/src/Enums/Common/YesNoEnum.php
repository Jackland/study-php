<?php

namespace App\Enums\Common;

use Framework\Enum\BaseEnum;

class YesNoEnum extends BaseEnum
{
    const YES = 1;
    const NO = 0;

    public static function getViewItems()
    {
        return [
            self::YES => __('是', [], 'common'),
            self::NO => __('否', [], 'common'),
        ];
    }
}
