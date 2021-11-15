<?php

namespace App\Enums\Margin;

use Framework\Enum\BaseEnum;

/**
 * tb_sys_margin_process.process_status
 *
 * Class MarginProcessStatus
 * @package App\Enums\Margin
 */
class MarginProcessStatus extends BaseEnum
{
    const approved = 1;// 审批通过，头款商品创建成功
    const ADVANCE_PRODUCT_SUCCESS = 2;// 头款商品购买完成，尾款商品创建成功
    const TO_BE_PAID = 3;// 尾款商品支付分销中
    const COMPLETED = 4;// 所有尾款商品销售完成
}
