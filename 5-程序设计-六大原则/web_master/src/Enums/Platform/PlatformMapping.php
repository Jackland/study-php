<?php

namespace App\Enums\Platform;

use Framework\Enum\BaseEnum;

class PlatformMapping extends BaseEnum
{
    const WAYFAIR = 1;
    const AMAZON = 2;
    const WALMART = 3;
    const EBAY = 4;
    const HOMEDEPOT = 5;
    const OVERSTOCK = 6;
    const NEW_EGG = 7;
    const MACYS = 8;
    const LOTTLE = 9;//乐天
    const YAHOO = 10;//雅虎
    const QOO10 = 14;
    const PMAIL = 15;
    const WOWMA = 17;//Wowma
    const FACEBOOK = 19;//Facebook
    const SHOPFIY = 20;//Shopify
    const JOYBUY = 21;//JOYBUY
    const MANOMANO = 22;//Manomano
    const CONFORAMA = 23;//Conforama
    const YM = 24;//YM
    const CDISCOUNT = 25;//Cdiscount

    public static function getViewItems()
    {
        return [
            static::WAYFAIR => 'Wayfair',
            static::AMAZON => 'Amazon',
            static::WALMART => 'Walmart',
            static::EBAY => 'eBay',
            static::HOMEDEPOT => 'HomeDepot',
            static::OVERSTOCK => 'overstock',
            static::NEW_EGG => 'NewEgg',
            static::MACYS => 'Macy\'s',
            static::LOTTLE => '乐天',
            static::YAHOO => '雅虎',
            static::QOO10 => 'QOO10',
            static::PMAIL => 'PMAIL',
            static::WOWMA => 'Wowma',
            static::FACEBOOK => 'Facebook',
            static::SHOPFIY => 'Shopify',
            static::JOYBUY => 'JOYBUY',
            static::MANOMANO => 'Manomano',
            static::CONFORAMA => 'Conforama',
            static::YM => 'YM',
            static::CDISCOUNT => 'Cdiscount',
        ];
    }
}

