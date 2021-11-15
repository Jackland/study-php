<?php

namespace App\Repositories\Safeguard;

use App\Enums\Safeguard\BillOrderType;
use App\Enums\Safeguard\SafeguardBillStatus;
use App\Models\Safeguard\SafeguardBill;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class SafeguardBillRepository
{
    /**
     * 查询保单状态
     *
     * @param int $billId
     * @return bool|int
     */
    public function getSafeguardBillStatus(int $billId)
    {
        $info = SafeguardBill::query()
            ->selectRaw('effective_time, expiration_time,status')
            ->where('id', '=', $billId)
            ->first();
        if (!$info) {
            return false;
        }
        //已取消
        if ($info->status == SafeguardBillStatus::CANCELED) {
            return SafeguardBillStatus::CANCELED;
        }
        //待生效
        if ($info->status == SafeguardBillStatus::PENDING) {
            return SafeguardBillStatus::PENDING;
        }
        //已失效
        if ($info->status == SafeguardBillStatus::ACTIVE && strtotime($info->expiration_time) < time()) {
            return SafeguardBillStatus::INVALID;
        }
        //保障中
        if ($info->status == SafeguardBillStatus::ACTIVE && strtotime($info->expiration_time) > time() && strtotime($info->effective_time) < time()) {
            return SafeguardBillStatus::ACTIVE;
        }
        return false;
    }

    /**
     * 根据销售订单ID查询保单
     * @param int $saleId
     * @param int|null $status
     * @return \Framework\Model\Eloquent\Builder[]|Builder[]|Collection|mixed[]
     */
    public function getSafeguardBillsBySaleId(int $saleId, ?int $status = null)
    {
        return SafeguardBill::query()
            ->with(['safeguardConfig'])
            ->where('order_id', $saleId)
            ->where('order_type', BillOrderType::TYPE_SALES_ORDER)
            ->when(!is_null($status), function ($query) use ($status) {
                $now = Carbon::now();
                switch ($status) {
                    case SafeguardBillStatus::ACTIVE:
                        $query->where('status', SafeguardBillStatus::ACTIVE)
                            ->where('effective_time', '<=', $now)
                            ->where('expiration_time', '>=', $now);
                        break;
                    case SafeguardBillStatus::CANCELED:
                        $query->where('status', SafeguardBillStatus::CANCELED);
                        break;
                    case SafeguardBillStatus::INVALID:
                        $query->where('status', SafeguardBillStatus::ACTIVE)
                            ->where('expiration_time', '<', $now);
                        break;
                    case SafeguardBillStatus::PENDING:
                        $query->where('status', SafeguardBillStatus::ACTIVE)
                            ->where('effective_time', '>', $now);
                        break;
                }
            })
            ->get()->map(function ($bill) {
                $bill->safeguardConfig->title = app(SafeguardConfigRepository::class)->geiNewestConfig($bill->safeguardConfig->rid, customer()->getCountryId())->title;
                return $bill;
            });
    }

    /**
     * 根据销售订单ID查询保单
     * @param $saleIds
     * @return \Framework\Model\Eloquent\Builder[]|Builder[]|Collection
     */
    public function getSafeguardBillsBySaleIds($saleIds)
    {
        return SafeguardBill::query()
            ->with(['safeguardClaim', 'safeguardConfig'])
            ->whereIn('order_id', $saleIds)
            ->where('order_type', BillOrderType::TYPE_SALES_ORDER)
            ->orderBy('safeguard_no', 'ASC')
            ->get()->groupBy('order_id');
    }
}
