<?php

namespace App\Services\SalesOrder\AutoPurchase;

use App\Models\Product\Product;
use Exception;
use ModelApiInventoryManagement;

class BuyerProductsStockPool
{
    /**
     * 某个buyer的所有产品囤货情况
     * @var array
     * [
     *    "item_code" => [
     *              [
     *                  'costId' => 1,
     *                  'qty' => 1,
     *                  'buyerId' => 1,
     *                  'sellerId' => 1,
     *                  'productId' => 1,
     *                  'ocOrderId' => 1,
     *                  'ocOrderProductId' => 1,
     *                  'item_code' => 1,
     *              ],
     *              ...
     *        ],
     *     ...
     * ]
     */
    private $data;

    /**
     * BuyerProductsStockPool constructor.
     * @param int $buyerId
     * @throws Exception
     */
    public function __construct(int $buyerId)
    {
        $this->getProductSkuUnbindStocks($buyerId);
    }

    /**
     * 获取某个产品的囤货情况
     * @param string $itemCode
     * @return array|mixed
     */
    public function getProductStocks(string $itemCode)
    {
        return $this->data[$itemCode] ?? [];
    }

    /**
     * 重置某个产品的囤货情况
     * @param string $itemCode
     * @param $stocks
     */
    public function resetProductStocks(string $itemCode, $stocks)
    {
        if (isset($this->data[$itemCode])) {
            $this->data[$itemCode] = $stocks;
        }
    }

    /**
     * 获取某个buyer的产品和囤货情况, 并按照sku分组
     * @param int $buyerId
     * @throws Exception
     */
    private function getProductSkuUnbindStocks(int $buyerId)
    {
        /** @var ModelApiInventoryManagement $modelApiInventoryManagement */
        $modelApiInventoryManagement = load()->model('api/inventory_management');

        // 获取这个buyer的所有产品的库存
        $unBindStocks = $modelApiInventoryManagement->getProductCostMap([$buyerId]);
        $productIdSkuMap = [];
        if (!empty($unBindStocks)) {
            // 获取产品的sku
            $productIds = array_column($unBindStocks, 'productId');
            $productIdSkuMap = Product::query()->whereIn('product_id', $productIds)->pluck('sku', 'product_id')->toArray();
        }

        // 将sku存入数据中，并按照sku分组
        $this->data = collect($unBindStocks)->map(function ($item) use ($productIdSkuMap) {
            $item['item_code'] = $productIdSkuMap[$item['productId']] ?? '';
            return $item;
        })->groupBy('item_code')->toArray();
    }
}
