<?php

namespace App\Services\Marketing;

use App\Enums\Marketing\CouponAuditStatus;
use App\Enums\Marketing\CouponStatus;
use App\Enums\Product\ProductTransactionType;
use App\Logging\Logger;
use App\Models\Marketing\CampaignOrder;
use App\Models\Marketing\Coupon;
use App\Models\Marketing\CouponTemplate;
use Exception;

class CouponService
{

    /**
     * 设置优惠券为已使用
     * @param int $orderId
     * @param int $couponId
     * @param float $orderAmount 货值金额
     * @throws Exception
     */
    public function setCouponUsed($orderId, $couponId, $orderAmount)
    {
        if (!$couponId) {
            return;
        }
        if (!$this->checkCouponCanUsed($couponId, $orderAmount)) {
            Logger::marketing('订单ID:' . $orderId . '不能使用优惠券ID:' . $couponId, 'error');
            throw new Exception('coupon can not use,with id ' . $couponId);
        }
        // 1. 设置优惠券为已使用 #36304增加status条件，防止并发导致使用同一优惠券
        $result = Coupon::where('id', $couponId)->where('status', CouponStatus::UNUSED)
            ->update([
                'status' => CouponStatus::USED,
                'order_id' => $orderId,
                'used_time' => date('Y-m-d H:i:s')]
            );
        if (!$result) {
            Logger::marketing('订单ID:' . $orderId . '不能使用优惠券ID:' . $couponId, 'error');
            throw new Exception('coupon can not use,with id ' . $couponId);
        }
    }

    /**
     * 设置优惠券为未使用
     * @param int $orderId 采购订单ID
     */
    public function cancelCouponUsed($orderId)
    {
        // 1. 设置优惠券为未已使用
        Coupon::where('order_id', $orderId)
            ->where('status', CouponStatus::USED)
            ->update(['status' => CouponStatus::UNUSED, 'order_id' => 0, 'used_time' => 0]);
    }


    /**
     * 判断优惠券时候可以使用
     * @param $orderAmount
     * @param $couponId
     * @return bool
     */
    public function checkCouponCanUsed($couponId, $orderAmount = null)
    {
        $coupon = Coupon::where(['audit_status' => CouponAuditStatus::PASS, 'is_deleted' => 0, 'customer_id' => customer()->getId()])->find($couponId);
        $now = date('Y-m-d H:i:s');
        if (!$coupon) {
            return false;
        }
        if ($coupon->effective_time > $now || $coupon->expiration_time < $now) {
            return false;
        }
        if ($coupon->status != CouponStatus::UNUSED) {
            return false;
        }
        if (!is_null($orderAmount) && bccomp($coupon->order_amount, $orderAmount, 2) == 1) {
            return false;
        }
        return true;
    }

    /**
     * 计算一个订单中多个产品的平均优惠券份额
     * @param float $denomination 优惠券优惠面额
     * @param array $orderProducts
     * @param int $precision
     * @return array
     */
    public function calculateCouponDiscount($denomination, $orderProducts, $precision = 2)
    {
        $orderProducts = collect($orderProducts)->where('product_type', 0);
        $count = $orderProducts->count();
        bcscale(4);
        $data = [];
        $total = 0;
        if ($count == 1) {
            $data[$orderProducts[0]['product_id']]['discount'] = $denomination;
            return $data;
        }
        foreach ($orderProducts as $item) {
            $price = $item['current_price'] ?? $item['price'];
            if ($item['type_id'] == ProductTransactionType::SPOT) {
                $price = $item['quote_amount'] ?? $item['spot_price'];
            }
            $total += $price * $item['quantity'];
        }

        foreach ($orderProducts as $key => $item) {
            $price = $item['current_price'] ?? $item['price'];
            if ($item['type_id'] == ProductTransactionType::SPOT) {
                $price = $item['quote_amount'] ?? $item['spot_price'];
            }
            $data[$item['product_id']]['product_id'] = $item['product_id'];
            if ($key >= $count - 1) {
                $data[$item['product_id']]['discount'] = 0;
                break;
            }
            $tmp = bcdiv($price * $item['quantity'], $total);
            $data[$item['product_id']]['discount'] = floor(bcmul($tmp, $denomination) * pow(10, $precision)) / pow(10, $precision);
        }
        $last = end($data);
        $someTotal = array_sum(array_column($data, 'discount'));
        $data[$last['product_id']]['discount'] = bcsub($denomination, $someTotal);
        return $data;
    }

    /**
     * 满送活动-赠送优惠券
     * @param int $orderId
     * @param int $buyerId
     */
    public function giftCoupon($orderId, $buyerId)
    {
        $campaignOrder = CampaignOrder::query()
            ->where('order_id', $orderId)
            ->where('coupon_template_id', '>', 0)
            ->get();
        if($campaignOrder->isEmpty()){
           return ;
        }
        foreach ($campaignOrder as $item) {
            // 1.发放优惠券
            // 达到该优惠券领取上限
            $buyerCouponNum = Coupon::where('customer_id', $buyerId)->where('coupon_template_id', $item->coupon_template_id)->count();
            $couponTemplate = CouponTemplate::select('per_limit')->find($item->coupon_template_id);
            if ($couponTemplate->per_limit != 0 && $buyerCouponNum >= $couponTemplate->per_limit) {
                Logger::marketing("{$orderId} 参加的活动 {$item->mc_id} 优惠券已送过", 'info');
                continue;
            }
            $couponId = app(CouponCenterService::class)->drawCoupon($item->coupon_template_id, $buyerId);
            // 2.更新coupon_id到oc_marketing_campaign_order表
            CampaignOrder::query()->where('id', $item->id)->update(['coupon_id' => $couponId]);
        }
    }
}
