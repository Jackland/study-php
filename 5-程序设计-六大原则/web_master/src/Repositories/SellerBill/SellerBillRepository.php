<?php

namespace App\Repositories\SellerBill;

use App\Models\SellerBill\SellerBill;
use App\Models\SellerBill\SellerBillTotal;
use Carbon\Carbon;

class SellerBillRepository
{

    /**
     * 获取seller当前账期账单
     *
     * @param int $sellerId
     * @return SellerBill|null
     */
    public function getCurrentBill(int $sellerId)
    {
        $now = Carbon::now();
        // 先取本期账单
        $sellerBill = SellerBill::query()
            ->where('seller_id', $sellerId)
            ->where('start_date', '<=', $now)->where('end_date', '>=', $now)
            ->first();
        if (!$sellerBill) {
            // 如果取不到，取最近的
            $sellerBill = SellerBill::query()
                ->where('seller_id', $sellerId)
                ->orderBy('end_date', 'desc')
                ->first();
        }
        return $sellerBill;
    }

    /**
     * 获取账单的明细
     *
     * @param int $billId
     * @param array $codes
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getBillTotalByCode(int $billId, array $codes = [])
    {
        return SellerBillTotal::query()->where('header_id', $billId)
            ->when(!empty($codes), function ($query) use ($codes) {
                $query->whereIn('code', $codes);
            })->get();
    }
}
