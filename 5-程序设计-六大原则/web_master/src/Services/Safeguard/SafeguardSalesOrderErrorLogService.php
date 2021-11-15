<?php

namespace App\Services\Safeguard;

use App\Enums\Safeguard\SafeguardSalesOrderErrorLogType;
use App\Models\Safeguard\SafeguardSalesOrderErrorLog;
use Carbon\Carbon;

class SafeguardSalesOrderErrorLogService
{
    /**
     * 添加日志
     *
     * @param int $salesOrderId 销售订单ID
     * @param int $typeId 必须在 SafeguardSalesOrderErrorLogType 中定义
     * @param string $remark 备注
     * @return false
     */
    public function addLog(int $salesOrderId, int $typeId, string $remark = '')
    {
        if (!in_array($typeId, SafeguardSalesOrderErrorLogType::getValues())) {
            return false;
        }
        return SafeguardSalesOrderErrorLog::create(
            [
                'sales_order_id' => $salesOrderId,
                'type_id' => $typeId,
                'remark' => $remark,
                'create_time' => Carbon::now(),
            ]
        );
    }
}
