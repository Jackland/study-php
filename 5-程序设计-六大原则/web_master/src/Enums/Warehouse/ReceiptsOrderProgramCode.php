<?php

namespace App\Enums\Warehouse;

use Framework\Enum\BaseEnum;

/**
 * tb_sys_receipts_order->program_code
 * 入库单版本号枚举
 *
 * Class ReceiptsOrderProgramCode
 * @package App\Enums\Warehouse
 */
class ReceiptsOrderProgramCode extends BaseEnum
{
    const PROGRAM_V_1 = '1.0';
    const PROGRAM_V_2 = '2.0';
    const PROGRAM_V_3 = '3.0';
}
