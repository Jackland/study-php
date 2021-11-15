<?php


namespace App\Enums\SalesOrder;

use Framework\Enum\BaseEnum;

class CustomerSalesExportStatus extends BaseEnum
{
    const EXPORTED_SUCCESS = 1; // 已同步
    const EXPORTED_FAILED = 2;  // 同步失败
    const EXPORTED_PENDING = 3; // 同步中
    const SYN_INIT = 0; // 未同步
    const SYN_SUCCESS = 1; // 同步成功
    const SYN_FAILED = 2; // 同步失败
}