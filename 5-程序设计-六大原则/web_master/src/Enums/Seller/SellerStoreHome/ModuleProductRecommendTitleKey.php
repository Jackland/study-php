<?php

namespace App\Enums\Seller\SellerStoreHome;

use Framework\Enum\BaseEnum;

class ModuleProductRecommendTitleKey extends BaseEnum
{
    const NEW_ARRIVALS = 1;
    const CLEARANCE = 2;
    const PROMOTION = 3;
    const SALE = 4;
    const COMING_SOON = 5;

    public static function getViewItems(): array
    {
        return [
            self::NEW_ARRIVALS => 'New Arrivals',
            self::CLEARANCE => 'Clearance',
            self::PROMOTION => 'Promotion',
            self::SALE => 'Sale',
            self::COMING_SOON => 'Coming Soon',
        ];
    }
}
