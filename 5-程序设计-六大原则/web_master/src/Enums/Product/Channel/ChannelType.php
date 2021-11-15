<?php

namespace App\Enums\Product\Channel;

use App\Models\Product\Channel\BaseChannel;
use App\Models\Product\Channel\BestSellersChannel;
use App\Models\Product\Channel\ComingSoonChannel;
use App\Models\Product\Channel\DropPriceChannel;
use App\Models\Product\Channel\FeaturedSellersChannel;
use App\Models\Product\Channel\NewArrivalsChannel;
use App\Models\Product\Channel\NewStoresChannel;
use App\Models\Product\Channel\WellStockedChannel;
use Framework\Enum\BaseEnum;
use InvalidArgumentException;

class ChannelType extends BaseEnum
{
    const NEW_ARRIVALS = 'new_arrivals';
    const BEST_SELLERS = 'best_sellers';
    const DROP_PRICE = 'price_drop';
    const WELL_STOCKED = 'well_stocked';
    const COMING_SOON = 'coming_soon';
    const NEW_STORES = 'new_stores';
    const FEATURED_SELLER = 'feature_stores';

    public static function getViewItems()
    {
        return [
            static::NEW_ARRIVALS => 'New Arrivals',
            static::BEST_SELLERS => 'Best Sellers',
            static::DROP_PRICE => 'Price Drop',
            static::WELL_STOCKED => 'Well-Stocked',
            static::COMING_SOON => 'Coming Soon',
            static::NEW_STORES => 'New Stores',
            static::FEATURED_SELLER => 'Featured Stores'
        ];
    }

    public static function getChanelModelByValue($value): BaseChannel
    {
        $map = [
            self::NEW_ARRIVALS => NewArrivalsChannel::class,
            self::BEST_SELLERS => BestSellersChannel::class,
            self::DROP_PRICE => DropPriceChannel::class,
            self::NEW_STORES => NewStoresChannel::class,
            self::WELL_STOCKED => WellStockedChannel::class,
            self::COMING_SOON => ComingSoonChannel::class,
            self::FEATURED_SELLER => FeaturedSellersChannel::class,
        ];
        if (!isset($map[$value])) {
            throw new InvalidArgumentException("{$value} 不存在");
        }
        return app()->make($map[$value]);
    }
}
