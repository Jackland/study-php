<?php

namespace App\Enums\Product;

use App\Models\Product\ProductLock;
use Framework\Enum\BaseEnum;

/**
 * Class ProductLockType
 * @package App\Enums\Product
 * @see ProductLock
 */
class ProductLockType extends BaseEnum
{
    const NORMAL = 0; // 普通
    const REBATE = 1; // 返点协议
    const MARGIN = 2; // 现货协议
    const FUTURES = 3; //期货协议
    const SPOT = 4; // 议价
    const SALES_ORDER = 5; // 纯物流
    const SELLER_INVENTORY_ADJUST = 6; // Seller库存调整

    /**
     * 库存管理合约中库存的类型
     *
     * @return int[]
     */
    public static function getStockManagementContractType()
    {
        return [static::MARGIN, static::FUTURES];
    }
}
