<?php

namespace App\Models\Seller\SellerStore\HomeModuleJson;

use App\Repositories\Product\ProductInfo\ProductInfoFactory;

trait ValidateProductAvailableTrait
{
    /**
     * 检验无效的产品，并给提示
     * @param callable $productIdsCB
     * @return \Closure
     */
    protected function validateProductIsAvailable(callable $productIdsCB): callable
    {
        return function ($attribute, $value, $fail) use ($productIdsCB) {
            if (!$this->shouldValidateProductAvailable()) {
                return;
            }
            $productIds = call_user_func($productIdsCB, $value);
            if (!$productIds) {
                return;
            }
            $baseInfos = (new ProductInfoFactory())
                ->withIds($productIds)
                ->getBaseInfoRepository()
                ->withUnavailable()
                ->getInfos();
            $unavailableInfos = [];
            foreach ($baseInfos as $baseInfo) {
                if (!$baseInfo->getIsAvailable()) {
                    $unavailableInfos[] = $baseInfo->sku;
                }
            }
            if ($unavailableInfos) {
                $fail($this->productNotAvailableMsg($unavailableInfos));
            }
        };
    }

    /**
     * 产品不可用时的错误提示
     * @param array $skus [sku1, sku2]
     * @return string
     */
    private function productNotAvailableMsg(array $skus): string
    {
        return __choice('产品:sku已下架，请移除这些产品！', count($skus), [
            'sku' => implode(', ', $skus)
        ], 'controller/seller_store');
    }
}
