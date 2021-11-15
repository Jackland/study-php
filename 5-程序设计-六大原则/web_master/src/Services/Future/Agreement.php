<?php

namespace App\Services\Future;

use App\Enums\Product\ProductTransactionType;
use App\Enums\Product\ProductType;
use App\Models\Futures\FuturesMarginAgreement;
use App\Models\Futures\FuturesMarginDelivery;
use App\Models\Order\OrderProduct;
use App\Models\Product\Product;

class Agreement
{

    /**
     * 释放期货协议
     * @param int $orderId 采购订单ID
     * @return int
     */
    public function unLockAgreement($orderId)
    {
        $orderProducts = OrderProduct::query()
            ->select('type_id', 'agreement_id', 'product_id')
            ->where('order_id', $orderId)
            ->where('type_id', ProductTransactionType::FUTURE)
            ->get();
        if ($orderProducts->isEmpty()) {
            return;
        }
        $productIds = $orderProducts->pluck('product_id')->toArray();
        $agreements = FuturesMarginAgreement:: query()
            ->select('id', 'is_bid', 'product_id')
            ->whereIn('id', $orderProducts->pluck('agreement_id')->toArray())
            ->get();
        foreach ($agreements as $item) {
            if ($item->is_bid) {
                continue;
            }
            FuturesMarginAgreement::where('id', $item->id)->update(['is_lock' => 0]);
            $advanceProductId = $this->getFuturesAdvanceProductId($item->id);
            // 下架删除该期货头款商品
            if (in_array($advanceProductId, $productIds)) {
                Product::query()
                    ->where('product_id', $advanceProductId)
                    ->where('product_type', ProductType::FUTURE_MARGIN_DEPOSIT)
                    ->update([
                        'status' => 0,
                        'is_deleted' => 1,
                        'date_modified' => date('Y-m-d H:i:s')
                    ]);
            }
        }
    }

    //获取期货头款商品ID
    public function getFuturesAdvanceProductId($agreementId)
    {
        return db()->table('oc_futures_margin_process')
            ->where('agreement_id', $agreementId)
            ->value('advance_product_id');
    }

}



