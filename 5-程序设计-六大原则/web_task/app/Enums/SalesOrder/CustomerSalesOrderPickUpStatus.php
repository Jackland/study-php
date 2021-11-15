<?php

namespace App\Enums\SalesOrder;

use App\Enums\BaseEnum;

/**
 * 销售订单状态
 * 对应 tb_sys_dictionary 表下的 CUSTOMER_ORDER_PICK_UP_STATUS 的值
 */
class CustomerSalesOrderPickUpStatus extends BaseEnum
{
    const PICK_UP_INFO_TBC = 10;//取货信息待确认 Pick-up Info TBC
    const IN_PREP = 20;//仓库备货中 In Prep
    const PICK_UP_TIMEOUT = 30;//超时未取货 Pick-up Timeout
}
