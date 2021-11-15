<?php


namespace App\Services\Marketing;


use App\Enums\Marketing\MarketingTimeLimitProductLogStatus;
use DB;

class MarketingTimeLimitDiscountService
{
    /**
     * 释放活动库存锁定
     * @param $orderId
     */
    public static function unLockTimeLimitProductQty($orderId)
    {
        DB::table('oc_marketing_time_limit_product_log')
            ->where('order_id', $orderId)
            ->update(['status' => MarketingTimeLimitProductLogStatus::ABANDONED]);
    }

}