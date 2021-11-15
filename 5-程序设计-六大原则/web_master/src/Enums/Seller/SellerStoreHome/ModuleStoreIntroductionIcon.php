<?php

namespace App\Enums\Seller\SellerStoreHome;

use Framework\Enum\BaseEnum;

class ModuleStoreIntroductionIcon extends BaseEnum
{
    const ICON_1 = 1;
    const ICON_2 = 2;
    const ICON_3 = 3;
    const ICON_4 = 4;
    const ICON_5 = 5;
    const ICON_6 = 6;
    const ICON_7 = 7;
    const ICON_8 = 8;
    const ICON_9 = 9;

    public static function getViewItems()
    {
        $publicUrl = aliases('@publicUrl');
        return [
            self::ICON_1 => $publicUrl . '/static/customerpartner/seller_store/home/images/infos/1.png',
            self::ICON_2 => $publicUrl . '/static/customerpartner/seller_store/home/images/infos/2.png',
            self::ICON_3 => $publicUrl . '/static/customerpartner/seller_store/home/images/infos/3.png',
            self::ICON_4 => $publicUrl . '/static/customerpartner/seller_store/home/images/infos/4.png',
            self::ICON_5 => $publicUrl . '/static/customerpartner/seller_store/home/images/infos/5.png',
            self::ICON_6 => $publicUrl . '/static/customerpartner/seller_store/home/images/infos/6.png',
            self::ICON_7 => $publicUrl . '/static/customerpartner/seller_store/home/images/infos/7.png',
            self::ICON_8 => $publicUrl . '/static/customerpartner/seller_store/home/images/infos/8.png',
            self::ICON_9 => $publicUrl . '/static/customerpartner/seller_store/home/images/infos/9.png',
        ];
    }
}
