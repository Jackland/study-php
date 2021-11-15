<?php

namespace App\Enums\Product;

use Framework\Enum\BaseEnum;

class ProductStatus extends BaseEnum
{
    const OFF_SALE = 0; // 已下架
    const ON_SALE = 1; // 已上架
    const WAIT_SALE = -1; //待上架

    public static function getViewItems()
    {
        return [
            self::WAIT_SALE => __('待上架', [], 'catalog/view/customerpartner/product/lists_index'),//To be available
            self::ON_SALE => __('已上架', [], 'catalog/view/customerpartner/product/lists_index'),//Available
            self::OFF_SALE => __('已下架', [], 'catalog/view/customerpartner/product/lists_index'),//Unavailable
        ];
    }

    /**
     * 未上架的状态
     * @return int[]
     */
    public static function notSale()
    {
        return [self::WAIT_SALE, self::OFF_SALE];
    }

    /**
     * 已上架的状态
     * @return int[]
     */
    public static function onSale()
    {
        return [self::ON_SALE];
    }

    /**
     * 全部状态
     * @return int[]
     */
    public static function allSale()
    {
        return [self::WAIT_SALE, self::OFF_SALE, self::ON_SALE];
    }
}
