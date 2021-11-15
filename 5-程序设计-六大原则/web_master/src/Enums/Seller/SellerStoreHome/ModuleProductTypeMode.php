<?php

namespace App\Enums\Seller\SellerStoreHome;

use Framework\Enum\BaseEnum;

class ModuleProductTypeMode extends BaseEnum
{
    const AUTO = 'auto';
    const MANUAL = 'manual';

    public static function getViewItems()
    {
        return [
            self::AUTO => __('自动推荐', [], 'enums/seller_store'),
            self::MANUAL => __('手工添加', [], 'enums/seller_store'),
        ];
    }
}
