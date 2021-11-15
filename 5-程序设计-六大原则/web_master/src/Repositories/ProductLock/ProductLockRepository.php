<?php

namespace App\Repositories\ProductLock;

use App\Models\Product\Product;
use App\Models\Product\ProductLock;
use App\Models\Product\ProductSetInfo;
use Exception;
use Illuminate\Support\Collection;
use ModelCatalogFuturesProductLock;
use ModelCatalogMarginProductLock;
use App\Enums\Product\ProductLockType;

class ProductLockRepository
{
    /**
     * 释放现货协议的锁定库存
     * @param int $agreementId 协议ID
     * @param int $type 退还库存的类型 [3:cancel 5:interrupt]
     * @SEE ModelCatalogMarginProductLock::TailOut
     * @throws Exception
     */
    public function releaseMarginLockQty(int $agreementId, int $type)
    {
        $pLock = ProductLock::query()->where('agreement_id', $agreementId)->first();
        if (!$pLock) return;
        /** @var ModelCatalogMarginProductLock $model */
        $model = load()->model('catalog/margin_product_lock');
        $model->TailOut($agreementId, $pLock->qty / $pLock->set_qty, ...func_get_args());
    }

    /**
     * 释放期货协议的锁定库存
     * @param int $agreementId 协议ID
     * @param int $type 退还库存的类型
     * @SEE ModelCatalogFuturesProductLock::TailOut
     * @throws Exception
     */
    public function releaseFuturesLockQty(int $agreementId, int $type)
    {
        $pLock = ProductLock::query()->where('agreement_id', $agreementId)->first();
        if (!$pLock) return;
        /** @var ModelCatalogFuturesProductLock $model */
        $model = load()->model('catalog/futures_product_lock');
        $model->TailOut($agreementId, $pLock->qty / $pLock->set_qty, ...func_get_args());
    }

    /**
     * 获取产品计算得到的Seller库存调整的锁库库存数量
     * @param int $productId
     * @return int
     * @throws Exception
     */
    public function getProductSellerInventoryAdjustComputeQty(int $productId)
    {
        $qty = (int)$this->getProductSellerInventoryAdjustQty($productId);
        $productModel = load()->model('common/product');
        $computeQty = [];
        $comboInfo = $productModel->getComboProduct($productId);
        array_map(function ($item) use ($productId, &$computeQty) {
            $realQty = (int)$this->getProductSellerInventoryAdjustQty($item['product_id'], [$productId]);
            $computeQty[] = (int)ceil($realQty / $item['qty']);
        }, $comboInfo);
        return $qty + (!empty($computeQty) ? max($computeQty) : 0);
    }

    /**
     * 获取产品Seller库存调整s的锁定库存
     * @param int $productId
     * @param array $excludeProductIds
     * @return int
     */
    public function getProductSellerInventoryAdjustQty(int $productId, array $excludeProductIds = [])
    {
        $list = ProductLock::query()
            ->where(function ($query) use ($productId) {
                $query->Where('parent_product_id', '=', $productId)
                    ->orWhere('product_id', '=', $productId);
            })
            ->where('type_id', '=', ProductLockType::SELLER_INVENTORY_ADJUST)
            ->where('qty', '>', 0)
            ->when(!empty($excludeProductIds), function ($q) use ($excludeProductIds) {
                $q->whereNotIn('parent_product_id', $excludeProductIds);
                $q->whereNotIn('product_id', $excludeProductIds);
            })
            ->get();
        $num = 0;
        if ($list) {
            $list->each(function ($item) use (&$num, $productId) {
                if ($item->product_id != $item->parent_product_id) {
                    if ($item->product_id == $productId) {
                        $num = (int)$num + $item->qty;
                    }
                    if ($item->parent_product_id == $productId) {
                        $num = (int)$num + ($item->qty / $item->set_qty);
                    }
                } else {
                    $num = (int)$num + $item->qty;
                }
            });
        }
        return (int)$num;
    }

    /**
     * 获取产品计算的理论锁定库存
     * @param Product|int $product
     * @return int
     * @see ModelCommonProduct::getProductComputeLockQty
     */
    public function getProductComputeLockQty($product): int
    {
        if (!($product instanceof Product)) {
            $product = Product::find($product);
        }
        if ($product->combo_flag) {
            $product->combos->each(function (ProductSetInfo $item) use (&$arr) {
                $qty = ProductLock::query()
                    ->where('product_id', $item->set_product_id)
                    ->where('is_ignore_qty', 0)
                    ->sum('qty');
                $arr[] = ceil($qty / $item->qty);
            });
        } else {
            $arr[] = ProductLock::query()
                ->where('product_id', $product->product_id)
                ->where('is_ignore_qty', 0)
                ->sum('qty');
        }

        return !empty($arr) ? intval(max($arr)) : 0;
    }

    /**
     * 获取批量产品计算的理论锁定库存
     * @param Collection|Product[] $products
     * @return array
     * @see ModelCommonProduct::getProductComputeLockQty
     */
    public function getProductsComputeLockQty(Collection $products): array
    {
        $productIds = $products->pluck('product_id')->toArray();
        $products->pluck('combos')->map(function ($item) use (&$productIds) {
            if (collect($item)->isNotEmpty()) {
                $productIds = array_merge($productIds, (collect($item)->pluck('set_product_id')->toArray()));
            }
        });
        $productIdLockQtyMap = ProductLock::query()
            ->whereIn('product_id', $productIds)
            ->where('is_ignore_qty', 0)
            ->groupBy(['product_id'])
            ->selectRaw('SUM(qty) as qty, product_id')
            ->get()
            ->pluck('qty', 'product_id');

        $productsComputeLockQtyMap = [];
        foreach ($products as $product) {
            $arr = [];
            if ($product->combo_flag) {
                $product->combos->each(function (ProductSetInfo $item) use (&$arr, $productIdLockQtyMap) {
                    $arr[] = ceil($productIdLockQtyMap->get($item->set_product_id, 0) / $item->qty);
                });
            } else {
                $arr[] =  $productIdLockQtyMap->get($product->product_id, 0);
            }
            $productsComputeLockQtyMap[$product->product_id] = !empty($arr) ? max($arr) : 0;
        }

        return $productsComputeLockQtyMap;
    }
}
