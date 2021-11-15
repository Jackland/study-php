<?php

namespace App\Enums\Product;

use Framework\Enum\BaseEnum;

//这个不是商品类型，而是新增和编辑商品时候，抽象出来的一种商品类型
class ProductCustomizeType extends BaseEnum
{
    const PRODUCT_NORMAL = 1; // 普通商品
    const PRODUCT_COMBO = 2; // combo
    const PRODUCT_PART = 3; // 部件

}
