<?php

namespace App\Enums\Cart;

use Framework\Enum\BaseEnum;

/**
 * 只针对购物车中type_id为0（普通商品）/ 作用为首次加入购物车以某种方式加入
 * Class CartAddCartType
 * @package App\Enums\Cart
 */
class CartAddCartType extends BaseEnum
{
    const DEFAULT_OR_OPTIMAL = 0; // 默认或最优价加入
    const NORMAL = 1; // 常规价加入
    const TIERED = 2; // 阶梯价加入
}
