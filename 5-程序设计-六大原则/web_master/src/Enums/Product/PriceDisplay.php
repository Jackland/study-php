<?php

namespace App\Enums\Product;

use Framework\Enum\BaseEnum;

class PriceDisplay extends BaseEnum
{
    const VISIBLE = 1;
    const INVISIBLE = 0;

    public static function getViewItems()
    {
        return [
            self::VISIBLE => __('可见', [], 'catalog/view/customerpartner/product/lists_index'),
            self::INVISIBLE => __('不可见', [], 'catalog/view/customerpartner/product/lists_index'),
        ];
    }
}
