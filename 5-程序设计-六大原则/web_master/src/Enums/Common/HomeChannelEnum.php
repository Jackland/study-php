<?php

namespace App\Enums\Common;

use App\Repositories\Product\Channel\Module\BestSellers;
use App\Repositories\Product\Channel\Module\DropPrice;
use App\Repositories\Product\Channel\Module\FeaturedStores;
use App\Repositories\Product\Channel\Module\NewArrivals;
use App\Repositories\Product\Channel\Module\WellStockedSearch;
use Framework\Enum\BaseEnum;
use ModelCatalogProductColumn;
use Registry;

class HomeChannelEnum extends BaseEnum
{
    const PRODUCT_RECOMMEND = 'product_recommend';//平台推荐feature products
    const NEW_ARRIVALS = 'new_arrivals';//新品到货 New Arrivals
    const BEST_SELLERS = 'best_sellers';//Best Sellers
    const WELL_STOCKED = 'well_stocked';//库存充足 well stocked
    const PRICE_DROP = 'price_drop';//降价差大 Big Price Drop
    const FEATURE_STORE = 'feature_store';//feature store
    const NEW_STORE = 'NEW_STORE';//feature store

    public static function getViewItems()
    {
        return [
            static::PRODUCT_RECOMMEND => 'products',
            static::NEW_ARRIVALS => 'newArrivals',
            static::BEST_SELLERS => 'bestSell',
            static::WELL_STOCKED => 'abundantInventorys',
            static::PRICE_DROP => 'bigPriceDrops',
            static::FEATURE_STORE => 'featureStores',
        ];

    }

    public static function getHomePageViewItems()
    {
        return [
            static::PRODUCT_RECOMMEND ,
            static::NEW_ARRIVALS,
            static::BEST_SELLERS,
            static::WELL_STOCKED ,
            static::PRICE_DROP,
            static::FEATURE_STORE,
        ];

    }

    public static function getChanelModelByValue($value)
    {
        $map = [
            self::PRODUCT_RECOMMEND => ModelCatalogProductColumn::class,
            self::NEW_ARRIVALS => NewArrivals::class,
            self::BEST_SELLERS => BestSellers::class,
            self::WELL_STOCKED => WellStockedSearch::class,
            self::PRICE_DROP => DropPrice::class,
            self::FEATURE_STORE => FeaturedStores::class,

        ];
        if (!isset($map[$value])) {
            throw new InvalidArgumentException("{$value} 不存在");
        }
        if($value == self::PRODUCT_RECOMMEND){
            return  load()->model('catalog/product_column');
        }
        return app()->make($map[$value]);
    }
}
