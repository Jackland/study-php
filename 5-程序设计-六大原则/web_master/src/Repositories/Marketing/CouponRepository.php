<?php

namespace App\Repositories\Marketing;

use App\Models\Marketing\Coupon;
use Illuminate\Database\Eloquent\Collection;

/**
 * Class CouponRepository
 * @package App\Repositories\Marketing
 */
class CouponRepository
{
    /**
     * 获取用户某些优惠券
     * @param array $couponIds
     * @param int $customerId
     * @param array $columns
     * @return Collection
     */
    public function getCustomerAvailableCouponsByIds(array $couponIds, int $customerId, array $columns = ['*'])
    {
        return Coupon::query()->available()
            ->where('customer_id', $customerId)
            ->whereKey($couponIds)
            ->select($columns)
            ->get();
    }

    /**
     * 获取最优使用的优惠券
     * @param int $customerId
     * @param int $amountRequirement
     * @param $limitDenomination
     * @param array $columns
     * @return \Illuminate\Database\Eloquent\Model|object|null
     */
    public function getBestDealAvailableCouponByCustomerId(int $customerId, $amountRequirement = 0, $limitDenomination = 0, array $columns = ['*'])
    {
        return Coupon::query()->available()
            ->where('customer_id', $customerId)
            ->where('order_amount', '<=', $amountRequirement)
            ->where('denomination', '<=', $limitDenomination)
            ->orderBy('denomination', 'desc')
            ->orderBy('expiration_time')
            ->select($columns)
            ->first();
    }

    /**
     * 获取用户满足订单金额的某些优惠券
     * @param int $customerId
     * @param float $amount
     * @param array|string[] $columns
     * @return Collection
     */
    public function getCustomerAvailableCouponsByOrderAmount(int $customerId, float $amount, array $columns = ['*'])
    {
        return Coupon::query()->available()
            ->where('customer_id', $customerId)
            ->where('order_amount', '<=', $amount)
            ->orderBy('denomination', 'desc')
            ->orderBy('order_amount', 'desc')
            ->select($columns)
            ->get();
    }

    /**
     * 根据订单ID获取优惠券ID
     * @param int $orderId 采购订单ID
     * @return Collection|Coupon[]
     */
    public function getCouponByOrderId($orderId)
    {
        return Coupon::query()
            ->select(['id','denomination','order_amount'])
            ->where('order_id', $orderId)
            ->get();
    }
}
