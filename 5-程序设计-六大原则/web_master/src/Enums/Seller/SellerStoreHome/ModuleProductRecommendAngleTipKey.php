<?php

namespace App\Enums\Seller\SellerStoreHome;

use Framework\Enum\BaseEnum;

class ModuleProductRecommendAngleTipKey extends BaseEnum
{
    const NEW = 1;
    const SALE = 2;
    const HOT = 3;

    public static function getViewItems()
    {
        return [
            self::NEW => 'NEW',
            self::SALE => 'SALE',
            self::HOT => 'HOT',
        ];
    }
}
