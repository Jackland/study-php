<?php

namespace App\Enums\Product;

use Framework\Enum\BaseEnum;

class ProductAuditType extends BaseEnum
{
    const PRODUCT_INFO = 1;
    const PRODUCT_PRICE = 2;

    public static function getViewItems()
    {
        return [
            self::PRODUCT_INFO => __('产品信息审核', [], 'catalog/view/customerpartner/product/lists_index'),//Approval for Product Information
            self::PRODUCT_PRICE => __('产品价格审核', [], 'catalog/view/customerpartner/product/lists_index'),//Approval for Product Pricing
        ];
    }
}
