<?php

namespace App\Services\FeeOrder;

use App\Components\BatchInsert;
use App\Enums\FeeOrder\FeeOrderFeeType;
use App\Enums\FeeOrder\FeeOrderStatus;
use App\Enums\FeeOrder\StorageFeeEndType;
use App\Enums\FeeOrder\StorageFeeStatus;
use App\Enums\Product\ProductTransactionType;
use App\Enums\YzcRmaOrder\RmaType;
use App\Logging\Logger;
use App\Models\FeeOrder\FeeOrder;
use App\Models\Link\OrderAssociated;
use App\Models\Margin\MarginAgreement;
use App\Models\Margin\MarginProcess;
use App\Models\Order\Order;
use App\Models\Order\OrderProduct;
use App\Models\Rma\YzcRmaOrder;
use App\Models\StorageFee\StorageFee;
use App\Repositories\FeeOrder\StorageFeeRepository;
use App\Repositories\Margin\MarginRepository;
use Illuminate\Database\Query\Expression;

/**
 * 仓租费用相关接口
 */
class StorageFeeService
{
    /**
     * 根据采购订单创建对应仓租
     * @param int $orderId 采购订单ID
     * @return bool
     */
    public function createByOrder($orderId)
    {
        if (StorageFee::query()->where('order_id', $orderId)->exists()) {
            Logger::storageFee(['该采购单已经入仓租', __FUNCTION__, $orderId], 'warning');
            Logger::alarm('采购单重复入仓租', ['见日志:storageFee']);
            return false;
        }

        $orderProducts = OrderProduct::query()->where('order_id', $orderId)->with(['product'])->get();
        $order = Order::query()->with(['buyer'])->find($orderId);

        $batchInsert = new BatchInsert();
        $batchInsert->begin(StorageFee::class, 500);
        $datetime = date('Y-m-d H:i:s');
        $storageFeeRepo = app(StorageFeeRepository::class);
        foreach ($orderProducts as $orderProduct) {
            $product = $orderProduct->product;
            $marginProcess = null;
            if ($orderProduct->type_id === ProductTransactionType::MARGIN) {
                // 现货
                $marginProcess = MarginProcess::query()
                    ->where('margin_id', '=', $orderProduct->agreement_id)
                    ->first();
                if (!$marginProcess) {
                    // 现货数据错误直接跳过
                    Logger::storageFee(['关联的现货协议不存在', $order->order_id, $orderProduct->product_id, $orderProduct->agreement_id], 'warning');
                    continue;
                }
                if ($marginProcess->rest_product_id == $orderProduct->product_id) {
                    // 现货尾款
                    // 尾款不用新插入体积等信息，只需要修改采购信息就可以，所以在之前先处理了
                    $updateMarginRestRes = $this->updateMarginRestStorage($orderProduct, $marginProcess);
                    if($updateMarginRestRes > 0){
                        // 处理完现货尾款数据后直接跳过
                        continue;
                    }
                } else {
                    // 如果是头款，product换成尾款产品，因为头款产品没有combo产品配比信息
                    $product = $marginProcess->restProduct;
                }
            }

            if (!$product) {
                Logger::storageFee(['关联产品不存在', $order->order_id, $orderProduct->product_id], 'warning');
                continue;
            }

            if (!$storageFeeRepo->canEnterStorageFee($product)) {
                // 不需要入仓租的跳过
                continue;
            }

            list($volume, $size) = $storageFeeRepo->calculateProductVolume($product);
            // 默认插入仓租数据，下面如果是复杂交易可能会修改
            $commonInsertData = [
                'buyer_id' => $order->customer_id,
                'country_id' => $order->buyer->country_id,
                'order_id' => $orderId,
                'order_product_id' => $orderProduct->order_product_id,
                'product_id' => $product->product_id,
                'product_sku' => $product->sku,
                'product_size_json' => json_encode($size),
                'volume_m' => $volume,
                'fee_total' => 0,
                'fee_paid' => 0,
                'fee_unpaid' => 0,
                'days' => 0,
                'status' => StorageFeeStatus::WAIT,
                'transaction_type_id' => ProductTransactionType::NORMAL,
                'agreement_id' => null,
                'created_at' => $order->date_added,
                'updated_at' => $datetime,
            ];
            $storageQuantity = $orderProduct->quantity;// 入仓租数量，普通采购按采购数量来
            if (!empty($marginProcess)
                && $orderProduct->type_id === ProductTransactionType::MARGIN // 现货
                && $marginProcess->rest_product_id != $orderProduct->product_id // 并且是头款
            ) {
                // 能走到这的必定是头款购买，尾款在上面已经处理过了
                /** @var MarginAgreement $marginAgreement */
                $marginAgreement = MarginAgreement::where('id', '=', $marginProcess->margin_id)->first();
                if (!$marginAgreement) {
                    // 现货数据错误直接跳过
                    Logger::storageFee(['关联的现货协议不存在', $marginProcess->margin_id], 'warning');
                    continue;
                }
                // 入对应协议数量的仓租
                $storageQuantity = $marginAgreement->num;
                // 替换插入仓租内数据
                // 现货头款不记录采购单信息
                $commonInsertData = array_merge($commonInsertData, [
                    'order_id' => 0,
                    'order_product_id' => 0,
                    'transaction_type_id' => ProductTransactionType::MARGIN,
                    'agreement_id' => $marginAgreement->id,
                ]);
            }

            for ($i = 0; $i < $storageQuantity; $i++) {
                $batchInsert->addRow($commonInsertData);
            }
        }
        $batchInsert->end();

        return true;
    }

    /**
     * 修改现货保证金尾款仓租
     *
     * @param OrderProduct $orderProduct
     * @param MarginAgreement $marginProcess
     * @return int 修改的数量，理论上应该与orderProduct内的数量一样
     */
    private function updateMarginRestStorage(OrderProduct $orderProduct,MarginProcess $marginProcess)
    {
        $updateStorageFees = app(StorageFeeRepository::class)
            ->getAgreementRestStorageFee(ProductTransactionType::MARGIN, $marginProcess->margin_id, $orderProduct->quantity);
        $res = 0;
        if($updateStorageFees->isNotEmpty()){
            // 把仓租采购数据换成尾款采购单数据
            $res = StorageFee::query()
                ->whereIn('id', $updateStorageFees->pluck('id')->toArray())
                ->update([
                    'order_id' => $orderProduct->order_id,
                    'order_product_id' => $orderProduct->order_product_id
                ]);
        }
        return $res;
    }

    /**
     * 根据完成的费用单，绑定仓租
     * @param array $feeOrderIds
     * @return bool
     */
    public function bindByFeeOrder(array $feeOrderIds)
    {
        $orders = FeeOrder::query()
            ->with('storageDetails')
            ->whereIn('id', $feeOrderIds)
            ->where('status', FeeOrderStatus::COMPLETE)
            ->get();
        $updateData = [];
        $updateDataOrderId = [];
        foreach ($orders as $order) {
            foreach ($order->storageDetails as $detail) {
                if (!$detail->sales_order_line_id) {
                    continue;
                }
                if (!isset($updateData[$detail->sales_order_line_id])) {
                    $updateData[$detail->sales_order_line_id] = [];
                    $updateDataOrderId[$detail->sales_order_line_id] = $order->order_id;
                }
                $updateData[$detail->sales_order_line_id][] = $detail->storage_fee_id;
            }
        }
        $this->bindStorageFee($updateData, $updateDataOrderId);

        return true;
    }

    /**
     * 根据绑定的销售单与采购单的关系，绑定仓租
     * @param array $orderAssociatedIds
     * @return bool
     */
    public function bindByOrderAssociated(array $orderAssociatedIds)
    {
        $models = OrderAssociated::query()->whereIn('id', $orderAssociatedIds)->get();

        $updateData = [];
        $updateDataOrderId = [];
        foreach ($models as $model) {
            if (!isset($updateData[$model->sales_order_line_id])) {
                $updateData[$model->sales_order_line_id] = [];
                $updateDataOrderId[$model->sales_order_line_id] = $model->sales_order_id;
            }
            $updateData[$model->sales_order_line_id][] = $model;
        }

        $updateDataTmp = [];
        $usedStorageFeeIds = []; // 本次已经被使用的仓租id，按照 order_product_id 分组
        foreach ($updateData as $salesOrderLineId => $models) {
            foreach ($models as $model) {
                /** @var OrderAssociated $model */
                if (!isset($updateDataTmp[$salesOrderLineId])) {
                    $updateDataTmp[$salesOrderLineId] = [];
                }
                if (!isset($usedStorageFeeIds[$model->order_product_id])) {
                    $usedStorageFeeIds[$model->order_product_id] = [];
                }

                $query = StorageFee::query()
                    ->where('order_product_id', $model->order_product_id)
                    ->whereIn('status', StorageFeeStatus::canBindStatus())
                    ->orderBy('id', 'asc');
                if ($usedStorageFeeIds[$model->order_product_id]) {
                    $query->whereNotIn('id', $usedStorageFeeIds[$model->order_product_id]);
                }
                $ids = $query->limit($model->qty)->pluck('id')->toArray();

                $usedStorageFeeIds[$model->order_product_id] = array_merge($usedStorageFeeIds[$model->order_product_id], $ids);
                $updateDataTmp[$salesOrderLineId] = array_merge($updateDataTmp[$salesOrderLineId], $ids);
            }
        }
        $updateData = $updateDataTmp;

        $this->bindStorageFee($updateData, $updateDataOrderId);

        return true;
    }

    /**
     * 绑定仓租
     * @param array $updateData 需要更新的内容，[$orderLineId => [$storageFeeId]]
     * @param array $updateDataOrderId 需要更新的销售单ID, [$orderLineId => $salesOrderId]
     */
    protected function bindStorageFee($updateData, $updateDataOrderId)
    {
        $datetime = date('Y-m-d H:i:s');
        foreach ($updateData as $orderLineId => $storageFeeIds) {
            if (!$storageFeeIds) {
                continue;
            }
            StorageFee::query()
                ->whereIn('id', $storageFeeIds)
                ->whereIn('status', StorageFeeStatus::canBindStatus())
                ->update([
                    'status' => StorageFeeStatus::BIND,
                    'sales_order_id' => $updateDataOrderId[$orderLineId],
                    'sales_order_line_id' => $orderLineId,
                    'updated_at' => $datetime,
                ]);
        }
    }

    /**
     * 销售单完成，结束仓租计算
     * @param array $salesOrderIds
     * @return bool
     */
    public function completeBySalesOrder(array $salesOrderIds)
    {
        if (!$salesOrderIds) {
            return true;
        }
        StorageFee::query()
            ->whereIn('sales_order_id', $salesOrderIds)
            ->whereIn('status', [StorageFeeStatus::BIND])
            ->update([
                'status' => StorageFeeStatus::COMPLETED,
                'end_type' => StorageFeeEndType::SALE,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        return true;
    }

    /**
     * 根据仓租id完成仓租
     *
     * @param $storageFeeIds
     * @param $endType
     * @return bool
     */
    public function completeByStorageFeeIds($storageFeeIds, $endType)
    {
        if (empty($storageFeeIds)) {
            return true;
        }
        StorageFee::query()
            ->whereIn('id', $storageFeeIds)
            ->whereIn('status', [StorageFeeStatus::WAIT])
            ->update([
                'status' => StorageFeeStatus::COMPLETED,
                'end_type' => $endType,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        return true;
    }

    /**
     * RMA完成，结束仓租计算
     * @param FeeOrder $feeOrder
     * @return bool
     */
    public function completeByRMA(FeeOrder $feeOrder)
    {
        $ids = $feeOrder->storageDetails()->pluck('storage_fee_id')->toArray();
        if (!$ids) {
            return true;
        }
        StorageFee::query()
            ->whereIn('id', $ids)
            ->whereNotIn('status', [StorageFeeStatus::COMPLETED])
            ->update([
                'status' => StorageFeeStatus::COMPLETED,
                'end_type' => StorageFeeEndType::RMA,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        return true;
    }

    /**
     * 销售单取消，解绑仓租
     * @param array $salesOrderIds
     * @return bool
     */
    public function unbindBySalesOrder(array $salesOrderIds)
    {
        if (!$salesOrderIds) {
            return true;
        }
        $models = StorageFee::query()
            ->whereIn('sales_order_id', $salesOrderIds)
            ->whereIn('status', StorageFeeStatus::canUnbindStatus())
            ->get();
        if ($models->isEmpty()) {
            return true;
        }
        $logInfo = [];
        $ids = [];
        foreach ($models as $model) {
            $logInfo[$model->id] = [$model->sales_order_id, $model->sales_order_line_id];
            $ids[] = $model->id;
        }
        Logger::storageFee(['解绑仓租', $logInfo]);

        StorageFee::query()
            ->whereIn('id', $ids) // 解绑仓租，根据id解绑而不是销售单直接解绑，是为了记录原仓租绑定信息，用于后续排查问题
            ->update([
                'status' => StorageFeeStatus::WAIT,
                'sales_order_id' => null,
                'sales_order_line_id' => null,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        return true;
    }

    /**
     * 根据销售订单新的绑定关系，解绑之前绑定的仓租
     * @param int $salesOrderId
     * @return bool
     */
    public function unbindBySalesOrderNewAssociated(int $salesOrderId)
    {
        $storageFeeModels = StorageFee::query()
            ->where('sales_order_id', $salesOrderId)
            ->whereIn('status', StorageFeeStatus::canUnbindStatus())
            ->get();
        if ($storageFeeModels->isEmpty()) {
            return true;
        }
        $logInfo = ['按新的绑定关系解绑仓租' => func_get_args()];
        $associatedModels = OrderAssociated::query()->where('sales_order_id', $salesOrderId)->get();
        $associatedKeyedQty = $associatedModels->mapWithKeys(function (OrderAssociated $item) {
            $key = implode('_', ['k', $item->sales_order_line_id, $item->order_product_id]);
            return [$key => $item->qty];
        });
        $needUnbindIds = [];
        foreach ($storageFeeModels as $item) {
            $key = implode('_', ['k', $item->sales_order_line_id, $item->order_product_id]);
            $qty = $associatedKeyedQty->get($key, 0);
            if ($qty <= 0) {
                // 不在新的绑定关系中的，或者qty超了的，表示需要解绑的
                $needUnbindIds[] = $item->id;
                $logInfo[$item->id] = [$item->sales_order_id, $item->sales_order_line_id];
            }
            if ($associatedKeyedQty->has($key)) {
                $associatedKeyedQty[$key] -= 1;
            }
        }
        if ($needUnbindIds) {
            Logger::storageFee($logInfo);
            StorageFee::query()
                ->whereIn('id', $needUnbindIds)
                ->update([
                    'status' => StorageFeeStatus::WAIT,
                    'sales_order_id' => null,
                    'sales_order_line_id' => null,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
        }
        return true;
    }

    /**
     * 费用单支付后
     * @param array $feeOrderIds
     * @return bool
     */
    public function payByFeeOrder(array $feeOrderIds)
    {
        $fn = __FUNCTION__;
        dbTransaction(function () use ($feeOrderIds, $fn) {
            $feeOrders = FeeOrder::query()
                ->where('status', FeeOrderStatus::COMPLETE)
                ->where('fee_type', FeeOrderFeeType::STORAGE)
                ->whereIn('id', $feeOrderIds)
                ->get();
            $ids = [];
            foreach ($feeOrders as $feeOrder) {
                foreach ($feeOrder->storageDetails as $storageDetail) {
                    // 单条仓租累加已付金额
                    StorageFee::query()
                        ->where('id', $storageDetail->storage_fee_id)
                        ->increment('fee_paid', $storageDetail->storage_fee);
                    $ids[] = $storageDetail->storage_fee_id;
                }
            }
            // 本次更新的所有仓租记录修改未付金额
            db(StorageFee::class)
                ->whereIn('id', $ids)
                ->update(['fee_unpaid' => new Expression('fee_total-fee_paid')]);
            // 检查本次更新是否存在超付的单子
            $ids = StorageFee::query()->where('fee_unpaid', '<', '0')
                ->whereIn('id', $ids)
                ->get(['id'])
                ->pluck('id')->toArray();
            if ($ids) {
                Logger::storageFee(['仓租支付出现负数', $fn, $feeOrderIds, $ids], 'warning');
            }
        });

        return true;
    }

    /**
     * 释放现货尾款仓租
     *
     * @param YzcRmaOrder $yzcRmaOrder
     * @return bool
     */
    public function unbindMarginRestStorageFee(YzcRmaOrder $yzcRmaOrder)
    {
        if ($yzcRmaOrder->order_type == RmaType::SALES_ORDER) {
            if (!$yzcRmaOrder->associate_product) {
                return false;
            }
            if ($yzcRmaOrder->associate_product->orderProduct->type_id != ProductTransactionType::MARGIN) {
                // 非现货退出
                return false;
            }
            // 判断协议是否过期，过期不回退仓租
            $isExpired = app(MarginRepository::class)->checkAgreementIsExpired($yzcRmaOrder->associate_product->orderProduct->agreement_id);
            if ($isExpired) {
                return false;
            }
            // 销售单
            // 获取销售单绑定的现货尾款仓租
            $canRmaStorageFeesIds = app(StorageFeeRepository::class)->getBindMarginRestStorageFeeByAssociated($yzcRmaOrder->associate_product);
        } elseif ($yzcRmaOrder->order_type == RmaType::PURCHASE_ORDER) {
            // 采购单
            if(!$yzcRmaOrder->yzcRmaOrderProduct){
                return false;
            }
            $yzcProduct = $yzcRmaOrder->yzcRmaOrderProduct;
            if($yzcProduct->orderProduct->type_id != ProductTransactionType::MARGIN){
                // 非现货退出
                return false;
            }
            // 判断协议是否过期，过期不回退仓租
            $isExpired = app(MarginRepository::class)->checkAgreementIsExpired($yzcProduct->orderProduct->agreement_id);
            if ($isExpired) {
                return false;
            }
            // 获取数量
            $rQty = $yzcProduct->quantity;
            // 获取需要退回的仓租
            $canRmaStorageFeesIds = StorageFee::query()
                ->select('id')
                ->where('order_id', $yzcRmaOrder->order_id)
                ->where('order_product_id', $yzcProduct->order_product_id)
                ->where('transaction_type_id', '=', ProductTransactionType::MARGIN)
                ->where('agreement_id', '=', $yzcProduct->orderProduct->agreement_id)
                ->where('status', StorageFeeStatus::WAIT)
                ->limit($rQty)
                ->pluck('id')
                ->toArray();
        }
        if (empty($canRmaStorageFeesIds)) {
            return false;
        }
        // 尾款仓租退回
        StorageFee::query()
            ->whereIn('id', $canRmaStorageFeesIds)
            ->where('transaction_type_id', '=', ProductTransactionType::MARGIN)
            ->update([
                'order_id' => 0,
                'order_product_id' => 0,
                'status' => StorageFeeStatus::WAIT,
                'sales_order_id' => null,
                'sales_order_line_id' => null,
            ]);
        return true;
    }
}
