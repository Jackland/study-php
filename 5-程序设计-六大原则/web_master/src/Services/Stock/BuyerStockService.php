<?php

namespace App\Services\Stock;

use App\Enums\Common\YesNoEnum;
use App\Enums\Stock\BuyerProductLockEnum;
use App\Models\Delivery\BuyerProductLock;
use App\Models\Link\OrderAssociatedPre;
use Illuminate\Support\Carbon;

/**
 * Buyer库存服务类
 */
class BuyerStockService
{
    /**
     * 获取buyer对应产品的锁定库存数量
     * @param int $buyerId
     * @param int $productId
     * @return int
     */
    public function getLockQuantity(int $buyerId, int $productId): int
    {
        return (int)BuyerProductLock::query()
            ->where([
                'buyer_id' => $buyerId,
                'product_id' => $productId,
                'is_processed' => 0,
            ])
            ->sum('qty');
    }

    /**
     * 根据order product id列表获取可用库存 参照之前的设计
     * @param array $orderProductIds
     * @return array
     */
    public function getLockQuantityIndexByOrderProductIdByOrderProductIds(array $orderProductIds): array
    {
        if (empty($orderProductIds)) {
            return [];
        }
        $costIdOrderProductIdMap = db('tb_sys_receive_line as rl')
            ->select(['op.order_product_id', 'scd.id as cost_id'])
            ->leftJoin('oc_order_product as op', function ($j) {
                $j->on('op.order_id', '=', 'rl.oc_order_id');
                $j->on('op.product_id', '=', 'rl.product_id');
            })
            ->leftJoin('tb_sys_cost_detail as scd', 'scd.source_line_id', '=', 'rl.id')
            ->whereIn('op.order_product_id', $orderProductIds)
            ->get()
            ->keyBy('cost_id')
            ->map(function ($item) {
                return $item->order_product_id;
            })
            ->toArray();
        $res = BuyerProductLock::query()
            ->whereIn('cost_id', array_keys($costIdOrderProductIdMap))
            ->where('is_processed', 0)
            ->get();
        $ret = [];
        foreach ($res as $buyerLock) {
            $orderProductId = $costIdOrderProductIdMap[$buyerLock->cost_id];
            $ret[$orderProductId] = ($ret[$orderProductId] ?? 0) + $buyerLock->qty;
        }

        return $ret;
    }

    /**
     * 根据sku获取每个sku的buyer锁定库存数
     * @param array $skus
     * @param int $buyerId
     * @return array
     */
    public function getLockQuantityIndexBySkuBySkus(array $skus, int $buyerId): array
    {
        return BuyerProductLock::query()->alias('bpl')
            ->select('p.sku')
            ->selectRaw('sum(bpl.qty) as lockQty')
            ->leftJoin('oc_product as p', 'p.product_id', '=', 'bpl.product_id')
            ->whereIn('p.sku', $skus)
            ->where('bpl.buyer_id', $buyerId)
            ->where('bpl.is_processed', 0)
            ->groupBy('p.sku')
            ->get()
            ->keyBy('sku')
            ->map(function ($item) {
                return (int)$item->lockQty;
            })
            ->toArray();
    }


    /**
     * 根据产品id获取每个产品的buyer锁定库存数
     * @param array $productIds
     * @param int $buyerId
     * @return array
     */
    public function getLockQuantityIndexByProductIdByProductIds(array $productIds, int $buyerId): array
    {
        return BuyerProductLock::query()->alias('bpl')
            ->select('bpl.product_id')
            ->selectRaw('sum(bpl.qty) as lockQty')
            ->whereIn('bpl.product_id', $productIds)
            ->where('bpl.buyer_id', $buyerId)
            ->where('bpl.is_processed', 0)
            ->groupBy('bpl.product_id')
            ->get()
            ->keyBy('product_id')
            ->map(function ($item) {
                return (int)$item->lockQty;
            })
            ->toArray();
    }

    /**
     * 销售订单预绑定库存锁定
     * @param string $runId
     * @param int $customerId
     */
    public function inventoryLockBySalesOrderPreAssociated(string $runId, int $customerId)
    {
        $productLockData = OrderAssociatedPre::query()->alias('ap')
            ->join('tb_sys_receive_line as rl', function ($join) {
                $join->on('ap.order_id', '=', 'rl.oc_order_id')
                    ->on('ap.product_id', '=', 'rl.product_id')
                    ->on('ap.buyer_id', '=', 'rl.buyer_id');
            })
            ->join('tb_sys_cost_detail as cd', function ($join) {
                $join->on('cd.source_line_id', '=', 'rl.id');
            })
            ->where('ap.run_id', $runId)
            ->where('ap.buyer_id', $customerId)
            ->where('ap.status', 0) // 预绑定
            ->where('ap.associate_type', 1) // 关联囤货库存
            ->select(['ap.id', 'ap.qty', 'ap.buyer_id', 'ap.product_id', 'cd.id as cost_id'])
            ->get();

        foreach ($productLockData as $data) {
            if (BuyerProductLock::query()
                ->where('is_processed', YesNoEnum::NO)
                ->where('buyer_id', $data->buyer_id)
                ->where('product_id', $data->product_id)
                ->where('cost_id', $data->cost_id)
                ->where('foreign_key', $data->id)
                ->where('type', BuyerProductLockEnum::INVENTORY_PRE_ASSOCIATED)
                ->exists()) {
                continue;
            }

            BuyerProductLock::query()->insert([
                'buyer_id' => $data->buyer_id,
                'product_id' => $data->product_id,
                'qty' => $data->qty,
                'type' => BuyerProductLockEnum::INVENTORY_PRE_ASSOCIATED,
                'is_processed' => YesNoEnum::NO,
                'create_time' => Carbon::now(),
                'create_user' => $customerId,
                'cost_id' => $data->cost_id,
                'foreign_key' => $data->id,
            ]);
        }
    }

    /**
     * 释放销售订单预绑定库存锁定
     * @param array $salesOrderIds
     * @param int $customerId
     */
    public function releaseInventoryLockBySalesOrderPreAssociated(array $salesOrderIds, int $customerId)
    {
        if (empty($salesOrderIds)) {
            return;
        }

        $orderAssociatedPreIds = OrderAssociatedPre::query()
            ->whereIn('sales_order_id', $salesOrderIds)
            ->where('buyer_id', $customerId)
            ->where('associate_type', 1) // 关联囤货库存
            ->pluck('id');

        BuyerProductLock::query()
            ->where('is_processed', YesNoEnum::NO)
            ->where('buyer_id', $customerId)
            ->where('type', BuyerProductLockEnum::INVENTORY_PRE_ASSOCIATED)
            ->whereIn('foreign_key', $orderAssociatedPreIds)
            ->update([
                'is_processed' => YesNoEnum::YES,
                'process_date' => Carbon::now(),
                'update_time' => Carbon::now(),
            ]);
    }

    /**
     * 释放采购订单预绑定库存锁定
     * @param array $orderIds
     * @param int $customerId
     */
    public function releaseInventoryLockByOrderPreAssociated(array $orderIds, int $customerId)
    {
        if (empty($orderIds)) {
            return;
        }

        $salesOrderIds = OrderAssociatedPre::query()
            ->whereIn('order_id', $orderIds)
            ->where('buyer_id', $customerId)
            ->where('associate_type', 2) // 关联新采购
            ->pluck('sales_order_id')
            ->toArray();

        $this->releaseInventoryLockBySalesOrderPreAssociated($salesOrderIds, $customerId);
    }
}
