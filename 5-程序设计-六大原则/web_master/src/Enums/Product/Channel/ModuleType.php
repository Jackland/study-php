<?php

namespace App\Enums\Product\Channel;

use App\Repositories\Product\Channel\Module\BaseInfo;
use App\Repositories\Product\Channel\Module\BestSellers;
use App\Repositories\Product\Channel\Module\ComingSoon;
use App\Repositories\Product\Channel\Module\ComingSoonSearch;
use App\Repositories\Product\Channel\Module\DropPrice;
use App\Repositories\Product\Channel\Module\FeaturedStores;
use App\Repositories\Product\Channel\Module\FutureGoods;
use App\Repositories\Product\Channel\Module\HotNew;
use App\Repositories\Product\Channel\Module\RecentPriceDrops;
use App\Repositories\Product\Channel\Module\NewArrivals;
use App\Repositories\Product\Channel\Module\NewStores;
use App\Repositories\Product\Channel\Module\SalesGrowthRate;
use App\Repositories\Product\Channel\Module\WellStocked;
use App\Repositories\Product\Channel\Module\WellStockedSearch;
use Framework\Enum\BaseEnum;
use InvalidArgumentException;

class ModuleType extends BaseEnum
{
    const NEW_STORE = 0;
    const HOT_NEW = 1;
    const NEW_ARRIVALS = 2;
    const FEATURED_STORES = 3;
    const SALES_GROWTH_RATE = 4;
    const BEST_SELLERS = 5;
    const RECENT_PRICE_DROPS = 6;
    const DROP_PRICE = 7;
    const WELL_STOCKED = 8;
    const WELL_STOCKED_SEARCH = 9;
    const COMING_SOON = 10;
    const FUTURE_GOODS = 11;
    const COMING_SOON_SEARCH = 12;

    public static function getModuleModelByValue($value): BaseInfo
    {
        $map = [
            self::NEW_STORE => NewStores::class,
            self::HOT_NEW => HotNew::class,
            self::NEW_ARRIVALS => NewArrivals::class,
            self::FEATURED_STORES => FeaturedStores::class,
            self::SALES_GROWTH_RATE => SalesGrowthRate::class,
            self::BEST_SELLERS => BestSellers::class,
            self::RECENT_PRICE_DROPS => RecentPriceDrops::class,
            self::DROP_PRICE => DropPrice::class,
            self::WELL_STOCKED => WellStocked::class,
            self::WELL_STOCKED_SEARCH => WellStockedSearch::class,
            self::COMING_SOON => ComingSoon::class,
            self::FUTURE_GOODS => FutureGoods::class,
            self::COMING_SOON_SEARCH => ComingSoonSearch::class,
        ];
        if (!isset($map[$value])) {
            throw new InvalidArgumentException("$value 不存在");
        }
        return app()->make($map[$value]);
    }
}
