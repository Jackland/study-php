<?php

namespace App\Enums\SalesOrder;

use App\Enums\BaseEnum;

/**
 * 销售订单状态
 * 对应 tb_sys_dictionary 表下的 CUSTOMER_ORDER_STATUS 的值
 */
class CustomerSalesOrderStatus extends BaseEnum
{
    const TO_BE_PAID = 1; // 订单明细状态除了Cancelled之外，其他订单明细处于Backordered时这个订单的状态为NewOrder表示缺货
    const BEING_PROCESSED = 2; // 订单明细状态除了Cancelled之外，其他订单明细处于Pending时这个订单的状态为Being Processed表示这个订单一切正常，可以发货
    const ON_HOLD = 4; // 表示这个订单存在问题，需要处理
    const CANCELED = 16; // 订单取消
    const COMPLETED = 32; // 订单完成
    const LTL_CHECK = 64; // 超大件确认等待
    const PENDING_CHARGES = 127; // 费用待支付
    const ASR_TO_BE_PAID = 128; // 待支付签收服务费
    const CHECK_LABEL = 129; // 美国dropship业务 check Label 状态更改
    const ABNORMAL_ORDER = 256; // 表示这个订单存在问题,无法同步omd，需要手动处理
    const WAITING_FOR_PICK_UP = 257; // Waiting for Pick-up 待取货
}
