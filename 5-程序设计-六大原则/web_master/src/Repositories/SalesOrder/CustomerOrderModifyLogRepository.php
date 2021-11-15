<?php

namespace App\Repositories\SalesOrder;

use App\Enums\ModifyLog\CommonOrderActionStatus;
use App\Enums\ModifyLog\CommonOrderProcessCode;
use App\Enums\SalesOrder\CustomerSalesOrderStatus;
use App\Models\SalesOrder\CustomerOrderModifyLog;
use App\Models\SalesOrder\CustomerSalesOrderCancel;
use Illuminate\Support\Str;

class CustomerOrderModifyLogRepository
{
    /**
     * @param $salesOrderId
     * @param string|null $salesOrderMemo
     * @return int|null 1-不保留绑定关系 0-保留绑定关系 null-没有取消过
     * @see ModelAccountCustomerOrder::getSalesOrderStatusLabel 逻辑参考
     */
    public function getCancelOrderRemoveBindStockStatus($salesOrderId, ?string $salesOrderMemo = '')
    {
        $modifyLog = CustomerOrderModifyLog::query()
            ->where('header_id', '=', $salesOrderId)
            ->where('order_type', '=', 1)
            ->where('process_code', '=', CommonOrderProcessCode::CANCEL_ORDER)
            ->where('status', '=', CommonOrderActionStatus::SUCCESS)
            ->orderByDesc('create_time')
            ->first();
        if ($modifyLog && $modifyLog->before_record) {
            // 这里写new order又用枚举，是因为后面new order换成了to be paid
            if (Str::contains($modifyLog->before_record,
                ['New Order', CustomerSalesOrderStatus::getDescription(CustomerSalesOrderStatus::TO_BE_PAID)])) {
                return null;
            }
            if (Str::contains($modifyLog->before_record,
                [
                    CustomerSalesOrderStatus::getDescription(CustomerSalesOrderStatus::BEING_PROCESSED),
                    CustomerSalesOrderStatus::getDescription(CustomerSalesOrderStatus::ON_HOLD),
                    CustomerSalesOrderStatus::getDescription(CustomerSalesOrderStatus::PENDING_CHARGES),
                ])) {
                return $modifyLog->remove_bind;
            }
        } elseif ($salesOrderMemo) {
            // 获取B2B后台订单操作记录
            if (Str::contains($salesOrderMemo, '1->16')) {
                return null;
            }
            if (Str::contains($salesOrderMemo, ['2->16', '4->16'])) {
                $cancelLog = CustomerSalesOrderCancel::query()->where('header_id', '=', $salesOrderId)->orderByDesc('id')->first();
                if ($cancelLog->connection_relation == 1) {
                    return 0;
                } elseif ($cancelLog->connection_relation == 0) {
                    //解除--Keep in stock
                    return 1;
                }
            }
        }
        return null;
    }
}
