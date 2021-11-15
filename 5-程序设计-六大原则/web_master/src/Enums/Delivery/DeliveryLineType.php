<?php

namespace App\Enums\Delivery;

use Framework\Enum\BaseEnum;

/**
 * tb_sys_delivery_line 表 type字段
 * Class DeliveryLineType
 * @package App\Enums\Delivery
 */
class DeliveryLineType extends BaseEnum
{
    const GENERAL = 1;//普通销售订单绑定出库
    const RMA = 2;//RMA类型出库(重发单出库、销售订单取消出库)
    const PURCHASE = 3;//采购订单出库
    const WHOLESALE = 4;//批发单出库
    const CWF = 5;//云送仓订单出库
    const BO = 6;//BO出库
    const RMA_CANCEL = 7;//取消重发出库
    const INVENTORY_LOSSES = 8; // 盘亏 -- 6991
}
