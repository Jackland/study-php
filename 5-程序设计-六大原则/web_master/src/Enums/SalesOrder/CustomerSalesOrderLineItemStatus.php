<?php

namespace App\Enums\SalesOrder;

use Framework\Enum\BaseEnum;

/**
 * 销售订单明细状态
 * 对应 tb_sys_dictionary 表下的 CUSTOMER_ITEM_STATUS 的值
 */
class CustomerSalesOrderLineItemStatus extends BaseEnum
{
    const PENDING = 1;//订单明细对应的SKU有货
    const SHIPPING = 2;//订单明细处于发货中
    const SHIPPED = 4;//订单明细完成发货
    const CANCELED = 8;//订单明细取消
    const ON_HOLD = 12;//订单明细有问题
    const BACK_ORDERED = 16;//订单明细处于缺货
    const INVALID_ITEM = 18;//订单明细中的SKU无效
    const CHECK_LABEL = 129;//美國dropship业务 check Label 状态改
    const DELETED = 150; // 已删除

    public static function getViewItems()
    {
        return [
            static::PENDING => 'Pending',
            static::SHIPPING => 'Shipping',
            static::SHIPPED => 'Shipped',
            static::CANCELED => 'Canceled',
            static::ON_HOLD => 'On Hold',
            static::BACK_ORDERED => 'BackOrdered',
            static::INVALID_ITEM => 'InvalidItem',
            static::CHECK_LABEL => 'Check Label',
            static::DELETED => 'Deleted',
        ];
    }
}
