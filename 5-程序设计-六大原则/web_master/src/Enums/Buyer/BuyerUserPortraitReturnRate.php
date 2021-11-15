<?php

namespace App\Enums\Buyer;

use Framework\Enum\BaseEnum;

class BuyerUserPortraitReturnRate extends BaseEnum
{
    const NA = 0;
    const HIGH = 1;
    const MIDDLE = 2;
    const LOW = 3;

    public static function getViewItems()
    {
        return [
            static::NA => __('N/A', [], 'common'),
            static::HIGH => __('高', [], 'common'),
            static::MIDDLE => __('中', [], 'common'),
            static::LOW => __('低', [], 'common'),
        ];
    }
}
