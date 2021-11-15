<?php

namespace App\Enums\SalesOrder;

use Framework\Enum\BaseEnum;

/**
 * 销售订单类型
 */
class CustomerSalesOrderMode extends BaseEnum
{
    const DROP_SHIPPING = 1; // 一件代发
    const PICK_UP = 3; // 上门取货
    const CLOUD_DELIVERY = 4; // 云送仓
    const PURE_LOGISTICS = 5; // 自发货(纯物流)订单
}
