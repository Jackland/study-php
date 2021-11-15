<?php

namespace App\Services\Marketing;

use App\Enums\Marketing\CouponStatus;
use App\Enums\Marketing\PlatformBillType;
use App\Models\Marketing\Coupon;
use App\Models\Marketing\PlatformBill;
use App\Models\Order\OrderProduct;
use App\Models\Rma\YzcRmaOrder;
use App\Repositories\Rma\RamRepository;

class PlatformBillService
{
    /**
     * 平台账单记一笔支出
     * @param int $orderId 采购订单ID
     */
    public function addBill($orderId)
    {
        $coupon = Coupon::query()
            ->where('order_id', $orderId)
            ->where('status', CouponStatus::USED)
            ->first();
        if (!$coupon) {
            return;
        }
        // 平台账单记一笔支出
        PlatformBill::query()
            ->insert([
                'type' => PlatformBillType::OUT,
                'order_id' => $orderId,
                'amount' => $coupon->denomination
            ]);
    }

    /**
     * 平台账单记一笔收入
     * @param int $rmaId
     * @return bool
     */
    public function backToPlatFormBill(int $rmaId)
    {
        $yzcRmaOrder = YzcRmaOrder::query()->with(['yzcRmaOrderProduct'])->find($rmaId);
        if ($yzcRmaOrder->yzcRmaOrderProduct->coupon_amount > 0) {
            //同一笔销售订单只能退一次
            $orderType = $yzcRmaOrder->order_type;
            $orderProductId = $yzcRmaOrder->yzcRmaOrderProduct->order_product_id;
            if ($orderType == 1) { //销售订单
                $fromCustomerOrderId = $yzcRmaOrder->from_customer_order_id;
                $buyerId = $yzcRmaOrder->buyer_id;
                $count = app(RamRepository::class)->calculateOrderProductApplyedRmaNum($buyerId, $fromCustomerOrderId, $orderProductId,$rmaId);
                if ($count > 0) {
                    return true;
                }
            } elseif ($orderType == 2) { //采购订单本来是可以退多次
                $orderProductDetail = OrderProduct::find($orderProductId);
                if ($orderProductDetail->coupon_amount <= 0 || empty($orderProductDetail->coupon_amount)) {
                    return true;
                }
                //获取此商品采购订单coupon已退金额
                $phurseOrderInfo = app(RamRepository::class)->getPhurseOrderRmaInfo($yzcRmaOrder->order_id, $orderProductId,$rmaId);
                $returnedPhurseCouponAmount = $phurseOrderInfo['all_phurse_coupon_amount'];
                $lastPhurseCouponAmount = max($orderProductDetail->coupon_amount - $returnedPhurseCouponAmount, 0);
                if ($lastPhurseCouponAmount == 0) {
                    return true;
                }
                $yzcRmaOrder->yzcRmaOrderProduct->coupon_amount = min($yzcRmaOrder->yzcRmaOrderProduct->coupon_amount, $lastPhurseCouponAmount);
            } else {
                return true;
            }

            PlatformBill::query()->insert([
                'type' => PlatformBillType::IN,
                'order_id' => $yzcRmaOrder->order_id,
                'product_id' => $yzcRmaOrder->yzcRmaOrderProduct->product_id,
                'rma_id' => $rmaId,
                'amount' => $yzcRmaOrder->yzcRmaOrderProduct->coupon_amount,
                'remark' => 'rma退优惠券',
            ]);
        }
        return true;
    }

}
