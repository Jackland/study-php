<?php

namespace App\Enums\Seller\SellerStoreHome;

use Framework\Enum\BaseEnum;

class ModuleProductTypeAutoSortType extends BaseEnum
{
    const HOT_SALE = 1;
    const NEW_ARRIVAL = 2;
    const HOT_DOWNLOAD = 3;
    const PRICE_LOW = 4;
    const PRICE_HIGH = 5;

    public static function getViewItems()
    {
        return [
            self::HOT_SALE => __('热卖产品在前', [], 'enums/seller_store'),
            self::NEW_ARRIVAL => __('新品到货在前', [], 'enums/seller_store'),
            self::HOT_DOWNLOAD => __('热门下载在前', [], 'enums/seller_store'),
            self::PRICE_LOW => __('价格最低在前', [], 'enums/seller_store'),
            self::PRICE_HIGH => __('价格最低在后', [], 'enums/seller_store'),
        ];
    }
}
