<?php

use App\Services\Stock\BuyerStockService;


class ModelApiInventoryManagement extends Model
{

    /**
     * 获取可用库存列表
     * @param array $buyerIdArr
     * @return array
     */
    public function getProductCostMap($buyerIdArr)
    {
        $productCostMap = db('tb_sys_cost_detail as scd')
            ->leftJoin('tb_sys_receive_line as srl', 'scd.source_line_id', '=', 'srl.id')
            ->leftJoin('oc_order_product as oop', function ($j) {
                $j->on('oop.order_id', '=', 'srl.oc_order_id');
                $j->on('oop.product_id', '=', 'srl.product_id');
            })
            ->whereIn('scd.buyer_id', $buyerIdArr)
            ->where([['scd.type', '=', 1], ['scd.onhand_qty', '>', 0]])
            ->select('scd.id', 'scd.original_qty', 'scd.buyer_id', 'scd.seller_id', 'srl.oc_order_id', 'srl.product_id', 'scd.sku_id', 'oop.order_product_id')
            ->get()
            ->toArray();
        $orderProductIdArr = array_column($productCostMap, 'order_product_id');
        $associateQtyArr = db('tb_sys_order_associated as soa')
            ->whereIn('soa.order_product_id', $orderProductIdArr)
            ->selectRaw('sum(soa.qty) as assQty,soa.order_product_id')
            ->groupBy(['soa.order_product_id'])
            ->get()
            ->keyby('order_product_id')
            ->map(function ($v) {
                return (array)$v;
            })
            ->toArray();
        $rmaQtyArr = db('oc_yzc_rma_order as yro')
            ->leftJoin('oc_yzc_rma_order_product as rop', 'rop.rma_id', '=', 'yro.id')
            ->whereIn('rop.order_product_id', $orderProductIdArr)
            ->where([
                ['yro.cancel_rma', '=', 0],
                ['rop.status_refund', '!=', 2],
                ['yro.order_type', '=', 2]
            ])
            ->selectRaw('sum(rop.quantity) as rmaQty,rop.order_product_id')
            ->groupBy(['rop.order_product_id'])
            ->get()
            ->keyby('order_product_id')
            ->map(function ($v) {
                return (array)$v;
            })
            ->toArray();
        $lockQtyArr = app(BuyerStockService::class)
            ->getLockQuantityIndexByOrderProductIdByOrderProductIds($orderProductIdArr);
        $unBindStockArr = [];
        foreach ($productCostMap as $productCost) {
            $buyQty = $productCost->original_qty;
            $orderProductId = $productCost->order_product_id;
            $assQty = $associateQtyArr[$orderProductId]['assQty'] ?? 0;
            $rmaQty = $rmaQtyArr[$orderProductId]['rmaQty'] ?? 0;
            $lockQty = $lockQtyArr[$orderProductId] ?? 0;
            $leftQty = $buyQty - $assQty - $rmaQty - $lockQty;
            if ($leftQty > 0) {
                $unBindStockArr[] = [
                    'costId' => $productCost->id,
                    'qty' => $leftQty,
                    'buyerId' => $productCost->buyer_id,
                    'sellerId' => $productCost->seller_id,
                    'productId' => $productCost->sku_id,
                    'ocOrderId' => $productCost->oc_order_id,
                    'ocOrderProductId' => $productCost->order_product_id
                ];
            }
        }

        return $unBindStockArr;
    }

}
