<?php

namespace App\Repositories\Buyer;

use App\Models\Buyer\BuyerSellerRecommend;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class BuySellerRecommendRepository
{
    /**
     * 获取 seller 最近一次的推荐
     * @param int $sellerId
     * @param array $with
     * @return BuyerSellerRecommend[]|Collection
     */
    public function getLastBatchBySeller(int $sellerId, $with = [])
    {
        $lastRecommend = BuyerSellerRecommend::query()
            ->select('created_at')
            ->where('seller_id', $sellerId)
            ->orderByDesc('created_at')
            ->first();
        if (!$lastRecommend || $lastRecommend->created_at->addDay(16)->lessThan(Carbon::now())) {
            // 无推荐，或该推荐已经超过16天
            return Collection::make([]);
        }
        return BuyerSellerRecommend::query()
            ->where('seller_id', $sellerId)
            ->where('created_at', $lastRecommend->created_at)
            ->with($with)
            ->get();
    }
}
