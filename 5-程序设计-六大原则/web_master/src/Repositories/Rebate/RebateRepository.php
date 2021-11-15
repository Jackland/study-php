<?php

namespace App\Repositories\Rebate;

use App\Enums\Rebate\AgreementOrderTypeEnum;
use App\Enums\Rebate\RebateAgreementResultEnum;
use App\Enums\SalesOrder\CustomerSalesOrderStatus;
use App\Enums\YzcRmaOrder\RmaType;
use App\Models\Link\OrderAssociated;
use App\Models\Order\OrderProduct;
use App\Models\Rebate\RebateAgreement;
use App\Models\Rebate\RebateAgreementItem;
use App\Models\Rebate\RebateAgreementOrder;
use App\Models\Rebate\RebateAgreementRequest;
use App\Models\Rebate\RebateAgreementTemplateItem;
use App\Models\Rma\YzcRmaOrder;
use Cart\Currency;
use Framework\Model\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class RebateRepository
{
    const MESSAGES = [
        'A' => '%s item(s) in this purchase order detail have a rebate total of %s that will be deducted from your refund. You are eligible to apply for a maximum refund of %s.',
        'B' => '%s item(s) in this RMA request have been involved in a Rebate Agreement (ID: %s)',
        'C' => '%s item(s) in this purchase order details have rebates. Applying for an RMA may affect the completion of your Rebate Agreement.',
        'D' => '%s of the returnable item(s) are involved in a Rebate Agreement with rebates of %s each, so you are eligible to apply for a maximum refund of %s each.',
        'D_EX' => 'For item(s) that were purchased without a rebate, a full refund can be applied. By default, the Marketplace will automatically first return items not involved in a Rebate Agreement.'
    ];


    /** @var Currency */
    private $currency;

    public function __construct()
    {
        $this->currency = app('registry')->get('currency');
    }

    /**
     * 查询该订单前的可参加返点协议数量
     * @param int $orderId
     * @param int $productId
     * @return int
     * @see ModelCustomerpartnerRmaManagement::getRebateOrderBefore()
     */
    public function getRebateOrderBefore(int $orderId, int $productId): int
    {
        // 先获取对应的order product
        $orderProduct = OrderProduct::query()
            ->where(['order_id' => $orderId, 'product_id' => $productId])
            ->first();
        $rebateItem = $this->getRebateItemInfo($orderId, $productId);
        $query = RebateAgreementOrder::query()
            ->where(['agreement_id' => $rebateItem->agreement_id])
            ->where('order_product_id', '<', $orderProduct->order_product_id);
        $queryRma = clone $query;
        $query = $query->where(['type' => AgreementOrderTypeEnum::PURCHASE]); // 正常购买数量
        $queryRma = $queryRma->where(['type' => AgreementOrderTypeEnum::RMA]); // RMA数量
        return (int)($query->sum('qty') - $queryRma->sum('qty'));
    }

    /**
     * 查询该订单可以参加返点协议的数量
     * @param int $orderId
     * @param int $productId
     * @return int
     */
    public function getRebateQty(int $orderId, int $productId): int
    {
        $query = RebateAgreementOrder::query()
            ->where([
                'order_id' => $orderId,
                'product_id' => $productId,
            ]);
        $queryRma = clone $query;
        $query = $query->where(['type' => AgreementOrderTypeEnum::PURCHASE]); // 正常购买
        $queryRma = $queryRma->where(['type' => AgreementOrderTypeEnum::RMA]); // rma
        return (int)($query->sum('qty') - $queryRma->sum('qty'));
    }

    /**
     * 根据订单和产品id获取rebate信息
     * @param int $orderId
     * @param int $productId
     * @return RebateAgreementItem|null 返回null表示没有参加返点
     */
    public function getRebateItemInfo(int $orderId, int $productId)
    {
        // 先获取对应的rebate order
        $rebateOrder = RebateAgreementOrder::query()
            ->with(['rebateAgreement'])
            ->where([
                'order_id' => $orderId,
                'product_id' => $productId,
                'type' => AgreementOrderTypeEnum::PURCHASE,
            ])
            ->first();
        if (!$rebateOrder) {
            return null;
        }
        return RebateAgreementItem::query()
            ->with(['rebateAgreement'])
            ->where([
                'agreement_id' => $rebateOrder->agreement_id,
                'product_id' => $productId,
            ])
            ->first();
    }

    /**
     * 校验是否已经申请返点
     * @param int $rebateAgreementId
     * @return bool
     */
    public function checkRequestRebate(int $rebateAgreementId): bool
    {
        return RebateAgreementRequest::query()->where(['agreement_id' => $rebateAgreementId])->exists();
    }

    /**
     * rebate 改版
     * @param int $orderId
     * @param int $productId
     * 返回结果: false => 没有参与返点 null => 参与了返点但是目前没有完成 array => 正常结果
     * @return array|false|null
     */
    public function getRebateDetailsInfo(int $orderId, int $productId)
    {
        bcscale(2);
        $rebateItem = $this->getRebateItemInfo($orderId, $productId);
        if (empty($rebateItem)) return false;  // 未参与rebate
        $rebate = $rebateItem->rebateAgreement; // rebate
        if (in_array($rebate->rebate_result,
            [
                RebateAgreementResultEnum::FAILED, // 协议失败
                RebateAgreementResultEnum::TERMINATED, // 协议终止
            ]
        )) {
            return false;
        }
        $beforeRebateQty = $this->getRebateOrderBefore($orderId, $productId);
        $rebateQty = $this->getRebateQty($orderId, $productId);
        if (!($this->checkNeedCalculateRebate($rebate) || $this->checkNeedReturnFeeRange($rebate))) {
            return null;
        }
        if ($beforeRebateQty >= $rebate->qty) {
            // 该订单前的可参加返点协议的数量已经大于返点数量，全款退
            return null;
        }
        $ret = [];
        if ($rebateQty <= $rebate->qty - $beforeRebateQty) {
            // 该订单全在返点协议里,需扣除返点金额
            // 该情况下currentqty 必然小于 rebateqty
            $actualQty = $rebateQty;
            for ($i = 1; $i <= $actualQty; $i++) {
                $ret[$i] = $rebateItem->rebate_amount;
            }
        } else {
            // 部分参与返点的情况
            $diffQty = (int)bcsub($rebate->qty, $beforeRebateQty);
            $actualQty = $diffQty; // 实际计算rebate的数量
            $extraQty = (int)bcsub($rebateQty, $diffQty); // 需要原价退款的数量
            for ($i = 1; $i <= $extraQty; $i++) { // 默认先退没有参与返点协议的商品
                $ret[$i] = 0;
            }
            for ($j = 0; $j < $actualQty; $j++) {  // 参与返点协议的商品
                $ret[$i + $j] = $rebateItem->rebate_amount;
            }
        }
        // 对于数据进行特殊处理 分开成销售订单和采购订单 销售单在后面
        // 销售单特指取消的销售单
        $orderProduct = OrderProduct::query()->where(['product_id' => $productId, 'order_id' => $orderId,])->first();
        $associates = $orderProduct->orderAssociates;
        // 找到全部的已完成销售单的数据
        $completeSalesOrderQuantity = (int)$associates->reduce(function ($carry, $item) {
            /** @var OrderAssociated $item */
            if ($item->customerSalesOrder->order_status == CustomerSalesOrderStatus::COMPLETED) {
                return (int)($carry + $item->qty);
            }
            return $carry;
        }, 0);
        // 截取掉完成销售单的部分
        $extraRet = array_slice($ret, 0, count($ret) - $completeSalesOrderQuantity);
        // 获取没有绑定销售单的数量
        $remainPurchaseOrderQuantity = (int)($orderProduct->quantity - $associates->sum('qty'));
        // array_slice方法会丢失key值
        $purchaseRet = array_slice($extraRet, 0, $remainPurchaseOrderQuantity); // 采购单可用数据
        $cancelRet = array_slice($extraRet, $remainPurchaseOrderQuantity); // 取消的可用数据 这里面还包含绑定的数据
        // 获取返点没完成之前的退款数量
        $rebateRma = $this->getProcessingRebateRmaInfo($orderId, $productId);
        $cancelRebateRma = 0; // 返点没完成之前的销售单退款数量
        $purchaseRebateRma = 0; // 返点没完成之前的采购单退款数量
        if ($rebateRma && $rebateRma->isNotEmpty()) {
            $cancelRebateRma = $rebateRma->reduce(function ($carry, $item) {
                /** @var YzcRmaOrder $item */
                if ($item->order_type == RmaType::SALES_ORDER) {
                    return (int)($carry + $item->associate_product->qty);
                } else {
                    return $carry;
                }
            }, 0);
            // 获取返点没完成之前的采购单退款数量
            $purchaseRebateRma = $rebateRma->reduce(function ($carry, $item) {
                /** @var YzcRmaOrder $item */
                if ($item->order_type == RmaType::PURCHASE_ORDER) {
                    return (int)($carry + $item->yzcRmaOrderProduct->quantity);
                } else {
                    return $carry;
                }
            }, 0);
        }
        // 1.取消销售单发起退款的数量
        $cancelAssociateApplyRma = $associates->filter(function (OrderAssociated $item) {
            if ($item->customerSalesOrder->order_status == CustomerSalesOrderStatus::CANCELED) {
                $rmaList = $item->rma_list;
                $ret = false;
                /** @var YzcRmaOrder $rma */
                foreach ($rmaList as $rma) {
                    if (
                        in_array($rma->yzcRmaOrderProduct->rma_type, [2, 3])
                        && $rma->cancel_rma == 0
                        && $rma->yzcRmaOrderProduct->status_refund == 1
                    ) {
                        $ret = true;
                        break;
                    }
                }
                return $ret;
            }
            return false;
        });
        $cancelRet = array_slice($cancelRet, (int)($cancelAssociateApplyRma->sum('qty') - $cancelRebateRma));
        // 2.采购单发起退款的数量
        $rmaList = YzcRmaOrder::query()
            ->select('ro.*')
            ->with(['yzcRmaOrderProduct'])
            ->alias('ro')
            ->leftJoin('oc_yzc_rma_order_product as rop', 'rop.rma_id', '=', 'ro.id')
            ->where([
                'ro.order_id' => $orderId,
                'rop.product_id' => $productId,
                'ro.order_type' => RmaType::PURCHASE_ORDER,
                'ro.cancel_rma' => 0,
                'rop.status_refund' => 1,
            ])
            ->get();
        $purchaseRmaQuantity = 0;
        // 获取在返点协议过程中rma的数量
        foreach ($rmaList as $rma) {
            $purchaseRmaQuantity += (int)$rma->yzcRmaOrderProduct->quantity;
        }
        $purchaseRet = array_slice($purchaseRet, (int)($purchaseRmaQuantity - $purchaseRebateRma));
        return [$cancelRet, $purchaseRet];
    }

    /**
     * 按照给定的数量 给定的价格计算出应改去除返点之后的金额
     * @param string $refundMoney
     * @param int $quantity
     * @param int $orderId
     * @param int $productId
     * @param bool $isPurchaseOrder
     * @return array
     */
    public function checkRebateRefundMoney(
        string $refundMoney,
        int $quantity,
        int $orderId,
        int $productId,
        bool $isPurchaseOrder = true
    )
    {
        $rebateInfo = $this->getRebateDetailsInfo($orderId, $productId);
        if ($rebateInfo === false) return [];
        $rebateInfo = $rebateInfo ?? [];
        $rebateInfo = $isPurchaseOrder ? ($rebateInfo[1] ?? []) : ($rebateInfo[0] ?? []);
        $rebateItem = $this->getRebateItemInfo($orderId, $productId);
        $rebate = $rebateItem->rebateAgreement;
        $rebateInfo = array_values($rebateInfo ?? []); // 重建key值
        if (empty($rebateInfo)) {
            $quantity = min($quantity, $this->getOrderCalculateRebateQuantity($orderId, $productId));
            return [
                'buyerMsg' => sprintf(static::MESSAGES['C'], $quantity),
                'sellerMsg' => sprintf(static::MESSAGES['B'], $quantity, $rebate->agreement_code),
            ];
        }
        bcscale(2);
        $currencyRebateInfo = array_slice($rebateInfo, 0, $quantity); // 截取本次应该需要讨论的数据
        $rebateCount = count(array_filter($currencyRebateInfo)); // 明细实际返点数量
        $rebateAmount = array_sum($currencyRebateInfo); // 明细实际返点金额
        $unitRefundMoney = bcdiv($refundMoney, $quantity); // 单价
        if ($this->checkNeedCalculateRebate($rebate)) {
            return [
                'buyerMsg' => sprintf(static::MESSAGES['C'], $rebateCount),
                'sellerMsg' => sprintf(static::MESSAGES['B'], $rebateCount, $rebate->agreement_code),
            ];
        }
        $rebateEnum = [];
        for ($i = 1; $i <= $quantity; $i++) {
            $rebateEnum[$i] = bcsub($i * $unitRefundMoney, array_sum(array_slice($currencyRebateInfo, 0, $i)));
        }
        $ret = $this->checkNeedReturnFeeRange($rebate) ? ['refundRange' => $rebateEnum] : [];
        if ($isPurchaseOrder) {
            $buyerMsg = sprintf(
                static::MESSAGES['D'],
                $rebateCount,
                $this->currency->formatCurrencyPrice($rebateItem->rebate_amount, session('currency')),
                $this->currency->formatCurrencyPrice(bcsub($unitRefundMoney, $rebateItem->rebate_amount), session('currency'))
            );
            if (count($rebateInfo) > $rebateCount) {
                $buyerMsg .= sprintf(static::MESSAGES['D_EX']);
            }
            $ret = array_merge(
                $ret,
                [
                    'buyerMsg' => $rebateCount > 0 ? $buyerMsg : '',
                    'sellerMsg' => $rebateCount > 0
                        ? sprintf(static::MESSAGES['B'], $rebateCount, $rebate->agreement_code)
                        : '',
                ]
            );
        } else {
            $ret = array_merge(
                $ret,
                [
                    'buyerMsg' => $rebateCount > 0
                        ? sprintf(
                            static::MESSAGES['A'],
                            $rebateCount,
                            $this->currency->formatCurrencyPrice($rebateAmount, session('currency')),
                            $this->currency->formatCurrencyPrice(bcsub($refundMoney, $rebateAmount), session('currency'))
                        )
                        : '',
                    'sellerMsg' => $rebateCount > 0
                        ? sprintf(static::MESSAGES['B'], $rebateCount, $rebate->agreement_code)
                        : '',
                ]
            );
        }
        return $ret;
    }

    /**
     * 获取对应订单计算出的返点数量
     * @param int $orderId
     * @param int $productId
     * @return int
     */
    private function getOrderCalculateRebateQuantity(int $orderId, int $productId): int
    {
        $rebateItem = $this->getRebateItemInfo($orderId, $productId);
        if (empty($rebateItem)) return 0;  // 未参与rebate
        $rebate = $rebateItem->rebateAgreement; // rebate
        $beforeRebateQty = $this->getRebateOrderBefore($orderId, $productId);
        $rebateQty = $this->getRebateQty($orderId, $productId);
        return (int)min($rebateQty, $rebate->qty - $beforeRebateQty);
    }

    /**
     * 获取在返点过程中产生的rma返点记录
     * @param int $orderId
     * @param int $productId
     * @return YzcRmaOrder[]|Builder[]|Collection|\Illuminate\Database\Query\Builder[]|\Illuminate\Support\Collection|null
     */
    private function getProcessingRebateRmaInfo(int $orderId, int $productId)
    {
        $rebateRmaIds = RebateAgreementOrder::query()
            ->where(['product_id' => $productId, 'order_id' => $orderId, 'type' => AgreementOrderTypeEnum::RMA])
            ->pluck('rma_id')
            ->toArray();
        if (empty($rebateRmaIds)) {
            return null;
        }
        return YzcRmaOrder::query()->whereIn('id', $rebateRmaIds)->get();
    }

    /**
     * 校验返点是否需要返点提示，区分返点的进度
     * @param RebateAgreement $rebate
     * @return bool
     */
    private function checkNeedCalculateRebate(RebateAgreement $rebate): bool
    {
        if (
            (
                // 该产品参与的返点协议已到期并达成，但Buyer还没有申请返点
                $rebate->rebate_result == RebateAgreementResultEnum::FULFILLED
            )
            || in_array(
                $rebate->rebate_result,
                [
                    // 该产品参与的返点协议已到期并达成，Buyer申请了返点但Seller还没有同意
                    RebateAgreementResultEnum::Processing,
                    // 该产品参与的返点协议已到期并达成，Buyer申请了返点但Seller拒绝
                    RebateAgreementResultEnum::REBATE_DECLINED,
                ]
            )
        ) {
            return true;
        }
        return false;
    }

    /**
     * 校验是否需要返回计算的可用费用范围
     * @param RebateAgreement $rebate
     * @return bool
     */
    private function checkNeedReturnFeeRange(RebateAgreement $rebate): bool
    {
        return $rebate->rebate_result == RebateAgreementResultEnum::REBATE_PAID;
    }

    /**
     * 查询seller所在rebate模板的复杂交易
     * @param int $sellerId
     * @return array
     */
    public function getRebateProductsBySellerId(int $sellerId): array
    {
        return RebateAgreementTemplateItem::query()->alias('rti')
            ->leftJoinRelations(['rebateAgreementTemplate as rt'])
            ->where([
                'rti.is_deleted' => 0,
                'rt.is_deleted' => 0,
                'rt.seller_id' => $sellerId
            ])
            ->groupBy('rti.product_id')
            ->pluck('product_id')
            ->toArray();
    }
}
