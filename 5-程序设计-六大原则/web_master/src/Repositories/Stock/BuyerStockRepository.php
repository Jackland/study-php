<?php

namespace App\Repositories\Stock;

use App\Enums\Common\YesNoEnum;
use App\Enums\Stock\BuyerProductLockEnum;
use App\Models\Delivery\BuyerProductLock;
use App\Models\Link\OrderAssociatedPre;
use App\Models\Product\Product;

class BuyerStockRepository
{
    /**
     * 获取销售订单预绑定库存锁定的数据 sku和qty
     * @param string $runId
     * @param int $customerId
     * @return array
     */
    public function getInventoryLockSkuQtyMapBySalesOrderPreAssociated(string $runId, int $customerId): array
    {
        $orderAssociatedPreIds = OrderAssociatedPre::query()
            ->where('run_id', $runId)
            ->where('buyer_id', $customerId)
            ->where('status', 0) // 预绑定
            ->where('associate_type', 1) // 关联囤货库存
            ->pluck('id');

        $productIdLockQtyMap = BuyerProductLock::query()
            ->whereIn('foreign_key', $orderAssociatedPreIds)
            ->where('is_processed', YesNoEnum::NO)
            ->where('type', BuyerProductLockEnum::INVENTORY_PRE_ASSOCIATED)
            ->where('buyer_id', $customerId)
            ->groupBy(['product_id'])
            ->selectRaw('product_id, SUM(qty) as qty')
            ->get()
            ->pluck('qty', 'product_id')
            ->toArray();

        $productIds = array_keys($productIdLockQtyMap);
        $products = Product::query()->whereIn('product_id', $productIds)->select(['product_id', 'sku'])->get();

        $skuLockQtyMap = [];
        foreach ($products as $product) {
            /** @var Product $product */
            $skuLockQtyMap[$product->sku] = $productIdLockQtyMap[$product->product_id] ?? 0 ;
        }

        return $skuLockQtyMap;
    }

    /**
     * 获取销售订单存在预锁定和锁定数量
     * @param array $orderIds
     * @param int $buyerId
     * @return array
     */
    public function getPreLockedSalesOrdersAndQtyByOrderIds(array $orderIds, int $buyerId): array
    {
        $orderAssociatedPres = OrderAssociatedPre::query()
            ->with('salesOrder')
            ->where('buyer_id', $buyerId)
            ->where('status', 0) // 预绑定
            ->where('associate_type', 1) // 关联囤货库存
            ->whereIn('sales_order_id', $orderIds)
            ->get();

        $buyerProductLockPreIdQtyMap = BuyerProductLock::query()
            ->whereIn('foreign_key', $orderAssociatedPres->pluck('id'))
            ->where('is_processed', YesNoEnum::NO)
            ->where('type', BuyerProductLockEnum::INVENTORY_PRE_ASSOCIATED)
            ->where('buyer_id', $buyerId)
            ->groupBy(['foreign_key'])
            ->selectRaw('foreign_key, SUM(qty) as qty')
            ->get()
            ->pluck('qty', 'foreign_key')
            ->toArray();

        $preLockedOrders = [];
        foreach ($orderAssociatedPres as $orderAssociatedPre) {
            $orderAssociatedPreLockQty = $buyerProductLockPreIdQtyMap[$orderAssociatedPre->id] ?? 0;
            if ($orderAssociatedPreLockQty == 0) {
                continue;
            }
            if (!isset($preLockedOrders[$orderAssociatedPre->sales_order_id])) {
                $salesOrder = $orderAssociatedPre->salesOrder;
                $salesOrder['locked_qty'] = 0;
            } else {
                $salesOrder = $preLockedOrders[$orderAssociatedPre->sales_order_id];
            }
            $salesOrder['locked_qty'] += $orderAssociatedPreLockQty;
            $preLockedOrders[$orderAssociatedPre->sales_order_id] = $salesOrder;
        }

        return $preLockedOrders;
    }
}
