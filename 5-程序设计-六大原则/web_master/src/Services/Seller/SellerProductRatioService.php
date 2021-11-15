<?php

namespace App\Services\Seller;

use App\Models\Seller\SellerProductRatio;
use Carbon\Carbon;
use Illuminate\Database\Query\Expression;

class SellerProductRatioService
{
    // 处理seller比率生效
    public function dealWithSellerProductRatioTakeEffect(?string $datetime = null,int $sellerId = 0)
    {
        $datetime = $datetime ?: Carbon::now()->toDateTimeString();
        $updateData = SellerProductRatio::query()
            ->when($sellerId, function ($query) use ($sellerId) {
                $query->where('seller_id', $sellerId);
            })
            ->whereNotNull('effective_time_next')
            ->where('effective_time_next', Carbon::parse($datetime)->format('Y-m-d H:00:00'))// 去除分秒，因为数据库存的最小单位是小时
            ->get();
        if ($updateData->isNotEmpty()) {
            SellerProductRatio::query()
                ->whereIn('id', $updateData->pluck('id')->toArray())
                ->update([
                    'product_ratio' => new Expression('product_ratio_next'),
                    'effective_time' => new Expression('effective_time_next'),
                    'product_ratio_next' => null,
                    'effective_time_next' => null,
                ]);
        }
        return $updateData;
    }
}
