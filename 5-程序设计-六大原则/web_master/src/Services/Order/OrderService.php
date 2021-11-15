<?php

namespace App\Services\Order;

use App\Enums\Order\OcOrderStatus;
use App\Helper\MoneyHelper;
use App\Logging\Logger;
use App\Repositories\Margin\MarginRepository;
use App\Models\Order\OrderProduct;
use App\Repositories\Order\OrderRepository;
use App\Services\Marketing\CouponService;

class OrderService
{
    /**
     * 取消费用单关联的采购订单
     *
     * @param int $feeOrderId
     */
    public function cancelOcOrderByFeeOrderId($feeOrderId)
    {
        $orders = app(OrderRepository::class)->getOrderByFeeOrderId($feeOrderId);
        foreach ($orders as $order) {
            if ($order->order_status_id == OcOrderStatus::TO_BE_PAID) {
                $this->cancelOrder($order->order_id);
            }
        }
    }

    /**
     * 取消订单
     *
     * @param int $orderId 采购订单ID
     * @throws \Exception
     */
    public function cancelOrder($orderId)
    {
        /** @var \ModelAccountOrder $accountOrder */
        $accountOrder = load()->model('account/order');
        $purchaseOrderLines = $accountOrder->getNoCancelPurchaseOrderLine($orderId);
        foreach ($purchaseOrderLines as $purchaseOrderLine) {
            $this->dealStock($purchaseOrderLine);
        }
        $accountOrder->cancelPurchaseOrder($orderId);

        // 设置优惠券为未使用
        app(CouponService::class)->cancelCouponUsed($orderId);
    }

    private function dealStock($purchaseOrder)
    {
        /** @var \ModelAccountOrder $accountOrder */
        $accountOrder = load()->model('account/order');
        //获取包销店铺
        $bxStoreArray = configDB('config_customer_group_ignore_check');
        //获取预出库明细
        $checkMargin = $accountOrder->checkMarginProduct($purchaseOrder);

        $checkAdvanceFutures = [];
        $checkRestMargin = $checkRestFutures = [];
        if ($purchaseOrder['type_id'] == 2) {
            $checkRestMargin = $accountOrder->checkRestMarginProduct($purchaseOrder);
        } elseif ($purchaseOrder['type_id'] == 3) {
            $checkRestFutures = $accountOrder->checkRestFuturesProduct($purchaseOrder); //校验是否是期货尾款
            if (empty($checkRestFutures)) {
                $checkAdvanceFutures = $accountOrder->checkFuturesAdvanceProduct($purchaseOrder);
            } //校验是否是期货头款
        }

        if (!empty($checkMargin)) {
            // 保证金店铺的头款产品
            // 1.更改上架以及combo影响的产品库存
            // 2.oc_order_lock表刪除保证金表数据 履约人表删除数据
            // 3.更改头款商品上架库存产品库存

            // 需要考虑期货转现货头款产品的情况 只有非期货转现货头款产品才发生退货
            if (!app(MarginRepository::class)->checkMarginIsFuture2Margin($checkMargin['margin_id'])) {
                $accountOrder->rebackMarginSuffixStore($checkMargin['product_id'], $checkMargin['num']);
            }
            $accountOrder->deleteMarginProductLock($checkMargin['margin_id']);
            $accountOrder->marginStoreReback($purchaseOrder['product_id'], $purchaseOrder['quantity']);
        } elseif (!empty($checkRestMargin) && !in_array($checkRestMargin['seller_id'], $bxStoreArray)) {
            // 保证金店铺的尾款产品
            // 1 .oc_order_lock表更改保证金表数据
            $accountOrder->updateMarginProductLock($checkRestMargin['margin_id'], $purchaseOrder['quantity'], $purchaseOrder['order_id']);
            //还到上架库存
            $accountOrder->reback_stock_ground($checkRestMargin, $purchaseOrder);
            //退还批次库存
            $preDeliveryLines = $accountOrder->getPreDeliveryLines($purchaseOrder['order_product_id']);
            foreach ($preDeliveryLines as $preDeliveryLine) {
                $accountOrder->reback_batch($preDeliveryLine);
            }
        } elseif (!empty($checkAdvanceFutures)) {
            $accountOrder->updateFuturesAdvanceProductStock($purchaseOrder['product_id']);
        } elseif (!empty($checkRestFutures)) {
            //期货尾款
            $futuresProductLockModel = load()->model('catalog/futures_product_lock');
            $futuresProductLockModel->TailIn($checkRestFutures['agreement_id'], $purchaseOrder['quantity'], $purchaseOrder['order_id'], 7);
            //退还批次库存
            $preDeliveryLines = $accountOrder->getPreDeliveryLines($purchaseOrder['order_product_id']);
            foreach ($preDeliveryLines as $preDeliveryLine) {
                $accountOrder->reback_batch($preDeliveryLine);
            }
        } else {
            $preDeliveryLines = $accountOrder->getPreDeliveryLines($purchaseOrder['order_product_id']);
            if (count($preDeliveryLines) > 0) {
                //外部店铺或者包销店铺退库存处理
                if (in_array($purchaseOrder['customer_id'], $bxStoreArray) || $purchaseOrder['accounting_type'] == 2) {
                    //判断是否为combo品
                    if ($purchaseOrder['combo_flag'] == 1) {
                        foreach ($preDeliveryLines as $preDeliveryLine) {
                            $accountOrder->rebackStock($purchaseOrder, $preDeliveryLine, true);
                        }
                        //非保证金combo退库存
                        $accountOrder->rebackComboProduct($purchaseOrder['product_id'], $purchaseOrder['quantity']);
                    } else {
                        //非combo品
                        foreach ($preDeliveryLines as $preDeliveryLine) {
                            $accountOrder->rebackStock($purchaseOrder, $preDeliveryLine, true);
                        }
                    }
                } else {
                    //内部店铺的cancel采购订单出库,服务店铺产品
                    //判断是否为combo品
                    if ($purchaseOrder['combo_flag'] == 1) {
                        foreach ($preDeliveryLines as $preDeliveryLine) {
                            $accountOrder->rebackStock($purchaseOrder, $preDeliveryLine, false);
                        }

                        //非保证金combo退库存
                        $accountOrder->rebackComboProduct($purchaseOrder['product_id'], $purchaseOrder['quantity']);
                    //}
                    } else {
                        foreach ($preDeliveryLines as $preDeliveryLine) {
                            $accountOrder->rebackStock($purchaseOrder, $preDeliveryLine, false);
                        }
                    }
                }
            } else {
                //没有预出库明细
                $msg = "[采购订单超时返还库存错误],采购订单明细：" . $purchaseOrder['order_product_id'] . ",未找到对应预出库记录";
                Logger::salesOrder($msg, 'error');
            }
        }
    }

    /**
     * 采购单与销售单指定数量绑定后的优惠金额
     * @param int $orderProductId
     * @param int $bindQuantity
     * @param int $precision
     * @return float[]|int[]
     */
    public function orderProductWillAssociateDiscountsAmount(int $orderProductId, int $bindQuantity, int $precision = 2)
    {
        /** @var OrderProduct $orderProduct */
        $orderProduct = OrderProduct::query()->with(['orderAssociates'])->find($orderProductId);
        if ($orderProduct->coupon_amount == 0 && $orderProduct->campaign_amount == 0) {
            return [
                'coupon_amount' => 0,
                'campaign_amount' => 0,
            ];
        }

        // 单个产品数量的优惠券和满减金额
        $singleCouponAmount = MoneyHelper::averageAmountFloor($orderProduct->coupon_amount, $orderProduct->quantity, $precision);
        $singleCampaignAmount = MoneyHelper::averageAmountFloor($orderProduct->campaign_amount, $orderProduct->quantity, $precision);

        // 已绑定的数量
        $boundQuantity = $orderProduct->orderAssociates->sum('qty');

        if ($boundQuantity + $bindQuantity == $orderProduct->quantity) {
            return [
                'coupon_amount' => $orderProduct->coupon_amount - $singleCouponAmount * $boundQuantity,
                'campaign_amount' => $orderProduct->campaign_amount - $singleCampaignAmount * $boundQuantity,
            ];
        } else {
            return [
                'coupon_amount' => $singleCouponAmount * $bindQuantity,
                'campaign_amount' => $singleCampaignAmount * $bindQuantity,
            ];
        }
    }

}
