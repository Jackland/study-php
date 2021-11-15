<?php

namespace App\Enums\Product\Channel;

use Framework\Enum\BaseEnum;

class ModuleProductsNums extends BaseEnum
{
    const NEW_STORE = 3;
    const HOT_NEW = 10;
    const NEW_ARRIVALS = 20;
    const FEATURED_STORES = 3;
    const SALES_GROWTH_RATE = 10;
    const BEST_SELLERS = 20;
    const RECENT_PRICE_DROPS = 10;
    const DROP_PRICE = 20;
    const WELL_STOCKED = 3;
    const WELL_STOCKED_SEARCH = 20;
    const COMING_SOON = 5;
    const FUTURE_GOODS = 10;
    const COMING_SOON_SEARCH = 20;

    public static function getViewItems(): array
    {
        return [
            ModuleType::NEW_STORE => self::NEW_STORE,
            ModuleType::HOT_NEW => self::HOT_NEW,
            ModuleType::NEW_ARRIVALS => self::NEW_ARRIVALS,
            ModuleType::FEATURED_STORES => self::FEATURED_STORES,
            ModuleType::SALES_GROWTH_RATE => self::SALES_GROWTH_RATE,
            ModuleType::BEST_SELLERS => self::BEST_SELLERS,
            ModuleType::RECENT_PRICE_DROPS => self::RECENT_PRICE_DROPS,
            ModuleType::DROP_PRICE => self::DROP_PRICE,
            ModuleType::WELL_STOCKED => self::WELL_STOCKED,
            ModuleType::WELL_STOCKED_SEARCH => self::WELL_STOCKED_SEARCH,
            ModuleType::COMING_SOON => self::COMING_SOON,
            ModuleType::FUTURE_GOODS => self::FUTURE_GOODS,
            ModuleType::COMING_SOON_SEARCH => self::COMING_SOON_SEARCH,
        ];
    }
}
