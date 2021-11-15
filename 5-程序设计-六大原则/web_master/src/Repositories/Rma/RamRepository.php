<?php

namespace App\Repositories\Rma;

use App\Enums\Common\YesNoEnum;
use App\Enums\FeeOrder\FeeOrderOrderType;
use App\Enums\SalesOrder\CustomerSalesOrderStatus;
use App\Enums\YzcRmaOrder\RmaType;
use App\Enums\YzcRmaOrder\RmaStatus;
use App\Enums\YzcRmaOrder\RmaRefundStatus;
use App\Helper\MoneyHelper;
use App\Models\FeeOrder\FeeOrder;
use App\Models\Link\OrderAssociated;
use App\Models\Order\OrderProduct;
use App\Models\Rma\YzcRmaOrder;
use App\Models\StorageFee\StorageFee;
use App\Repositories\FeeOrder\StorageFeeRepository;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use App\Enums\YzcRmaOrder\RmaApplyType;

class RamRepository
{
    /**
     * 获取采购订单预计的仓租费
     * @param int $orderProductId
     * @param int $qty
     * @return string|null
     */
    public function getPurchaseOrderCalculateStorageFee(int $orderProductId, int $qty)
    {
        $orderProductInfo = OrderProduct::query()->with(['order'])->find($orderProductId);
        $order = $orderProductInfo->order;
        // 获取所有可用的仓租信息表
        $storageFeeRepo = app(StorageFeeRepository::class);
        $canRmaStorageFeeIds = Arr::get(
            $storageFeeRepo->getCanRMAStorageFeeIdsByOrder(
                $order->order_id,
                [$orderProductId => $qty]
            ),
            $orderProductId
        );
        if (empty($canRmaStorageFeeIds)) {
            return null;
        }
        return $storageFeeRepo->getNeedPayByStorageFeeIds($canRmaStorageFeeIds);
    }

    /**
     * 获取仓租费
     * @param int $associateId
     * @return float|int|null
     */
    public function getSalesOrderCalculateStorageFee(int $associateId)
    {
        $storageFeeRepo = app(StorageFeeRepository::class);
        $canRmaStorageFeeIds = $storageFeeRepo
            ->getBoundStorageFeeIdsByAssociated($associateId);
        if (empty($canRmaStorageFeeIds)) {
            return null;
        }
        return $storageFeeRepo->getNeedPayByStorageFeeIds($canRmaStorageFeeIds);
    }

    /**
     * 校验rma是否需要付仓租费
     * 1.采购订单rma必然需要付仓租
     * 2.取消的销售单如果是第一次申请退货退款也需要付仓租费
     * ps: 如果关联的采购单id没有仓租明细 也不需要付仓租费
     * @param int $rmaId rmaid
     * @return bool
     */
    public function checkRmaNeedReturnStorageFee(int $rmaId): bool
    {
        $rma = YzcRmaOrder::find($rmaId);
        // 没有仓租不需要收仓租
        if (!StorageFee::query()->where('order_id', $rma->order_id)->exists()) {
            return false;
        }
        // 采购订单要收仓租
        if ($rma->order_type == RmaType::PURCHASE_ORDER) {
            return true;
        }
        // 被取消的 rma不能收仓租
        if ($rma->cancel_rma == 1) {
            return false;
        }
        // 重发单也不能收仓租
        if (!in_array($rma->yzcRmaOrderProduct->rma_type, [2, 3])) {
            return false;
        }
        // 同意过也不能收仓租
        if ($rma->yzcRmaOrderProduct->status_refund == 1) {
            return false;
        }
        return $this->checkNeedReturnStorageFee($rma->associate_product->id, [$rmaId]);
    }

    /**
     * 校验销售订单明细是否需要付仓租费
     * @param int $associatedId 销售单绑定明细id
     * @param array $excludeRmaIds 排除掉rma id部分
     * @return bool
     */
    public function checkNeedReturnStorageFee(int $associatedId, array $excludeRmaIds = []): bool
    {
        $associated = OrderAssociated::find($associatedId);
        $salesOrder = $associated->customerSalesOrder;
        if ($salesOrder->order_status == CustomerSalesOrderStatus::COMPLETED) {
            return false;
        }
        $rmaList = $associated->rma_list;
        if (!empty($excludeRmaIds)) {
            $rmaList = $rmaList->whereNotIn('id', $excludeRmaIds);
        }
        // 取消掉 销售单如果是第一次申请退货退款 即没有退款记录 要付仓租费
        $ret = true;
        foreach ($rmaList as $rma) {
            /** @var YzcRmaOrder $rma */
            if (
                $rma->cancel_rma == 0 // 没有取消
                && in_array($rma->yzcRmaOrderProduct->rma_type, [2, 3]) // 退款 或者 重发又退款
                && $rma->yzcRmaOrderProduct->status_refund == 1  // 同意退款
            ) {
                $ret = false;
                break;
            }
        }
        return $ret;
    }

    /**
     * 校验取消销售单rma是否是第一次退款
     * @param int $rmaId
     * @return bool
     */
    public function checkSalesOrderRmaFirstRefund(int $rmaId): bool
    {
        $associate = YzcRmaOrder::query()->find($rmaId);
        if (!$associate || !isset($associate->associate_product->rma_list)) {
            return true;
        }
        $associateRmaList = $associate->associate_product->rma_list;
        $ret = true;
        foreach ($associateRmaList as $rma) {
            /** @var YzcRmaOrder $rma */
            if (
                $rma->cancel_rma == 0 // 没有取消
                && in_array($rma->yzcRmaOrderProduct->rma_type, [2, 3]) // 退款 或者 重发又退款
                && $rma->yzcRmaOrderProduct->status_refund == 1  // 同意退款
            ) {
                $ret = false;
                break;
            }
        }
        return $ret;
    }

    /**
     * 校验取消销售单rma是否是第一次退款
     * @param int $associatedId
     * @return bool
     */
    public function checkSalesOrderRmaFirstRefundByAssociateId(int $associatedId): bool
    {
        $associateRmaList = OrderAssociated::query()->find($associatedId)->rma_list;
        $ret = true;
        foreach ($associateRmaList as $rma) {
            /** @var YzcRmaOrder $rma */
            if (
                $rma->cancel_rma == 0 // 没有取消
                && in_array($rma->yzcRmaOrderProduct->rma_type, [2, 3]) // 退款 或者 重发又退款
                && $rma->yzcRmaOrderProduct->status_refund == 1  // 同意退款
            ) {
                $ret = false;
                break;
            }
        }
        return $ret;
    }

    /**
     * 根据rma id获取费用单信息
     * @param int $rmaId rma id
     * @return FeeOrder|\Framework\Model\Eloquent\Builder|\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model|object|null
     */
    public function getFeeOrder(int $rmaId)
    {
        return FeeOrder::query()
            ->where('order_id', $rmaId)
            ->where('order_type', FeeOrderOrderType::RMA)
            ->first();
    }

    /**
     * 获取申请过rma并且seller同意的销售单数量
     *
     * @param int $customerId
     * @param int $fromCustomerOrderId
     * @param int $orderProductId
     * @param int $rmaId
     *
     * @return int
     */
    public function calculateOrderProductApplyedRmaNum($customerId, $fromCustomerOrderId, $orderProductId, $rmaId = 0)
    {
        return db('oc_yzc_rma_order as t1')
            ->leftJoin('oc_yzc_rma_order_product as t2', 't1.id', '=', 't2.rma_id')
            ->leftJoin('tb_sys_customer_sales_order as t3', 't1.from_customer_order_id', '=', 't3.order_id')
            ->selectRaw("t1.order_id,t1.from_customer_order_id,t3.id as sales_order_id,t2.order_product_id")
            ->where([
                't1.buyer_id' => $customerId,
                't1.order_type' => RmaType::SALES_ORDER,
                't1.seller_status' => RmaStatus::RMA_STATUS_PROCESSED,
                't1.cancel_rma' => 0,//未取消
                't2.status_refund' => RmaRefundStatus::PRODCUT_RMA_AGREED,
                't2.order_product_id' => $orderProductId,
                't1.from_customer_order_id' => $fromCustomerOrderId,
            ])
            ->whereIn('t3.order_status', [CustomerSalesOrderStatus::CANCELED, CustomerSalesOrderStatus::COMPLETED])
            ->whereIn('t2.rma_type', RmaApplyType::getRefund())
            ->when(($rmaId > 0), function (Builder $query) use ($rmaId) {
                $query->where('t1.id', '<>', $rmaId);
            })
            ->count();
    }

    /**
     * 获取采购订单RMA部分，活动和优惠券扣减总金额
     *
     * @param int $orderId
     * @param int $orderProductId
     * @param int $rmaId
     * @return array
     */
    public function getPhurseOrderRmaInfo($orderId, $orderProductId,$rmaId = 0)
    {
        $result = db('oc_yzc_rma_order as ro')
            ->leftJoin('oc_yzc_rma_order_product as rp', 'ro.id', '=', 'rp.rma_id')
            ->where('ro.order_type', RmaType::PURCHASE_ORDER)
            ->where('rp.status_refund', RmaRefundStatus::PRODCUT_RMA_AGREED)
            ->where('ro.seller_status', 2)
            ->where('ro.cancel_rma', 0)
            ->whereIn('rp.rma_type', RmaApplyType::getRefund())
            ->where('ro.order_id', $orderId)
            ->where('order_product_id', $orderProductId)
            ->when(($rmaId > 0), function (Builder $query) use ($rmaId) {
                $query->where('ro.id', '<>', $rmaId);
            })
            ->selectRaw('sum(rp.coupon_amount) as all_phurse_coupon_amount,sum(rp.campaign_amount) as all_phurse_campaign_amount')
            ->first();
        return [
            'all_phurse_coupon_amount' => !empty($result->all_phurse_coupon_amount) ? $result->all_phurse_coupon_amount : 0,
            'all_phurse_campaign_amount' => !empty($result->all_phurse_campaign_amount) ? $result->all_phurse_campaign_amount : 0,
        ];
    }

    /**
     * 获取采购订单RMA部分，申请了未处理+未取消的活动和优惠券金额
     *
     * @param int $orderId
     * @param int $orderProductId
     * @param int $rmaId
     * @return array
     */
    public function getPhurseOrderNoHandleRmaInfo($orderId, $orderProductId, $rmaOrderId = 0)
    {
        $result = db('oc_yzc_rma_order as ro')
            ->leftJoin('oc_yzc_rma_order_product as rp', 'ro.id', '=', 'rp.rma_id')
            ->where('ro.order_type', RmaType::PURCHASE_ORDER)
            ->where('rp.status_refund', RmaRefundStatus::PRODUCT_RMA_ORIGIN)
            //->where('ro.seller_status', 2)
            ->where('ro.cancel_rma', 0)
            ->where('ro.order_id', $orderId)
            ->where('order_product_id', $orderProductId)
            ->when(($rmaOrderId > 0), function (Builder $query) use ($rmaOrderId) {
                $query->where('ro.rma_order_id', '<>', $rmaOrderId);
            })
            ->selectRaw('sum(rp.coupon_amount) as all_phurse_coupon_amount,sum(rp.campaign_amount) as all_phurse_campaign_amount')
            ->first();
        return [
            'all_phurse_coupon_amount' => !empty($result->all_phurse_coupon_amount) ? $result->all_phurse_coupon_amount : 0,
            'all_phurse_campaign_amount' => !empty($result->all_phurse_campaign_amount) ? $result->all_phurse_campaign_amount : 0,
        ];
    }

    /**
     * 获取销售单，活动和优惠券扣减总金额,这个是RMA活动优惠券专用方法，
     *
     * @param int $orderId
     * @param int $productId
     *
     * @return array
     */
    public function getSalesOrderBindInfo($orderId, $productId)
    {
        $result = db('tb_sys_order_associated')
            ->where('order_id', $orderId)
            ->where('product_id', $productId)
            ->selectRaw('sum(coupon_amount) as all_sales_coupon_amount,sum(campaign_amount) as all_sales_campaign_amount')
            ->first();
        return [
            'all_sales_coupon_amount' => !empty($result->all_sales_coupon_amount) ? $result->all_sales_coupon_amount : 0,
            'all_sales_campaign_amount' => !empty($result->all_sales_campaign_amount) ? $result->all_sales_campaign_amount : 0,
        ];
    }

    /**
     * 根据数量获取活动和优惠券可退份额
     *
     * @param int $orderId
     * @param int $productId
     * @param int $orderProductId
     * @param int $returnAbleQty 可退总数量
     * @param int $isJapan
     * @param int $rmaOrderId
     *
     * @return array
     */
    public function getReturnCouponAndCampaign($orderId, $productId, $orderProductId, $returnAbleQty, $isJapan,$rmaOrderId = 0)
    {
        bcscale(2);
        $ret = [];
        for ($i = 1; $i <= $returnAbleQty; $i++) {
            $tempDiscount = $this->calculateRmaDiscountByQty($orderId, $productId, $orderProductId, $i, $returnAbleQty, $isJapan,$rmaOrderId);
            $ret[$i] = $tempDiscount['split_coupon_amount'] + $tempDiscount['split_campaign_amount'];
        }
        return $ret;
    }

    /**
     * 根据数量获取活动和优惠券可退份额
     *
     * @param int $orderId
     * @param int $productId
     * @param int $orderProductId
     * @param int $returnQty     退的数量
     * @param int $returnAbleQty 可退总数量
     * @param int $isJapan
     * @param int $rmaOrderId 更新rma调用
     *
     * @return array
     */
    public function calculateRmaDiscountByQty($orderId, $productId, $orderProductId, $returnQty, $returnAbleQty, $isJapan,$rmaOrderId = 0)
    {
        $result['split_coupon_amount'] = 0;
        $result['split_campaign_amount'] = 0;
        if ($returnAbleQty < $returnQty) {
            return $result;
        }
        $orderProductInfo = OrderProduct::find($orderProductId);
        if ($returnQty < $returnAbleQty) {
            if ($orderProductInfo->coupon_amount > 0) {
                $result['split_coupon_amount'] = $returnQty * MoneyHelper::averageAmountFloor($orderProductInfo->coupon_amount,
                        $orderProductInfo->quantity, $isJapan ? 0 : 2);
            }
            if ($orderProductInfo->campaign_amount > 0) {
                $result['split_campaign_amount'] = $returnQty * MoneyHelper::averageAmountFloor($orderProductInfo->campaign_amount,
                        $orderProductInfo->quantity, $isJapan ? 0 : 2);
            }
        } else {
            $splitCampaignAmount = $splitCouponAmount = 0;
            $salesOrderDiscount = $this->getSalesOrderBindInfo($orderId, $productId);
            $purchaseOrderDiscount = $this->getPhurseOrderRmaInfo($orderId, $orderProductId);
            if ($orderProductInfo->campaign_amount > 0) {
                $splitCampaignAmount = $orderProductInfo->campaign_amount - $salesOrderDiscount['all_sales_campaign_amount']
                    - $purchaseOrderDiscount['all_phurse_campaign_amount'];
            }
            if ($orderProductInfo->coupon_amount > 0) {
                $splitCouponAmount = $orderProductInfo->coupon_amount - $salesOrderDiscount['all_sales_coupon_amount']
                    - $purchaseOrderDiscount['all_phurse_coupon_amount'];
            }

            if ($rmaOrderId > 0) {
                $noHandleRmaInfo = $this->getPhurseOrderNoHandleRmaInfo($orderId, $orderProductId, $rmaOrderId);
                $splitCouponAmount -= $noHandleRmaInfo['all_phurse_coupon_amount'] ?? 0;
                $splitCampaignAmount -= $noHandleRmaInfo['all_phurse_capaign_amount'] ?? 0;
            }

            $result['split_coupon_amount'] = max($splitCouponAmount, 0);
            $result['split_campaign_amount'] = max($splitCampaignAmount, 0);
        }

        return $result;
    }

    /**
     * 传入2 ， 10 返回 [ 1 => 5, 2 => 10]
     * @param string $refundMoney
     * @param int $quantity
     * @return array
     */
    public function getRefundRange(string $refundMoney, int $quantity)
    {
        bcscale(2);
        $unitRefund = bcdiv($refundMoney, $quantity);
        $ret = [];
        for ($i = 1; $i <= $quantity; $i++) {
            $ret[$i] = bcmul($i, $unitRefund);
        }
        return $ret;
    }


    /**
     * 采购订单:获取某个订单的RMA信息 PS:不关联yzcRmaOrderProduct,因为查询条件中用不到了，
     * 查的是采购订单+销售订单，此方法改自于getALLRMAIDSByOrderProduct
     * @param int $OrderId
     * @param int $orderProductId
     * @return array
     */
    public function getPurchaseOrderRmaWithSumInfo(int $OrderId, int $orderProductId = 0)
    {
        $rmaList = YzcRmaOrder::query()->alias('t1')
            ->leftJoinRelations('yzcRmaOrderProduct as t2')
            ->selectRaw("t1.id as rma_id,t1.rma_order_id,t1.order_id,t1.buyer_id")
            ->where([
                't1.order_id' => $OrderId,
            ])
            ->when($orderProductId > 0, function ($q) use ($orderProductId) {
                $q->where('t2.order_product_id', $orderProductId);
            })
            ->get();

        return obj2array($rmaList);

    }

    /**
     * 获取采购订单RMA成功的总和(不一定通用)
     * @param array $orderProductIds
     * @return int
     */
    public function getPurchaseOrderRmaAmount(array $orderProductIds)
    {
        if (empty($orderProductIds)) {
            return 0;
        }

        $returnAmount =  YzcRmaOrder::query()->alias('a')
            ->leftJoin('oc_yzc_rma_order_product as b', 'a.id', '=', 'b.rma_id')
            ->where('a.order_type', RmaType::SALES_ORDER) //销售单绑定的采购单的金额，本质是采购单的rma，因为单纯的采购单rma，没有绑定信息
            ->where('a.cancel_rma', YesNoEnum::NO)
            ->whereIn('b.order_product_id', $orderProductIds)
            ->where('b.status_refund', RmaRefundStatus::PRODCUT_RMA_AGREED)
            ->whereIn('b.rma_type', RmaApplyType::getRefund())
            ->sum('b.actual_refund_amount');

        return $returnAmount ? $returnAmount : 0;
    }

}
