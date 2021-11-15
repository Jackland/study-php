<?php

namespace App\Enums\YzcRmaOrder;

use Framework\Enum\BaseEnum;

/**
 * 返金状态
 * oc_yzc_rma_order_product.status_refund
 *
 * Class RmaOrderProductStatusRefund
 * @package App\Enums\YzcRmaOrder
 */
class RmaOrderProductStatusRefund extends BaseEnum
{
    const DEFAULT = 0;//初始状态1
    const APPROVE = 1;//同意
    const REJECT = 2;//拒绝
}
