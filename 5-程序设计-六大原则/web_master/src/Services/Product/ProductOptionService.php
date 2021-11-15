<?php

namespace App\Services\Product;

use App\Models\Product\Option\ProductOption;
use App\Models\Product\Option\ProductOptionValue;
use App\Models\Product\ProductAssociate;

class ProductOptionService
{

    /**
     * 新增商品时,写入初始化数据
     *
     * @param int $productId
     * @param int $optionId
     * @param int $optionValueId
     * @return bool
     */
    public function insertProductOptionValue($productId, $optionId, $optionValueId)
    {
        $productOptionId = ProductOption::query()
            ->insertGetId(['product_id' => $productId, 'option_id' => $optionId, 'required' => 0]);

        if ($productOptionId) {
            ProductOptionValue::query()->insert([
                'product_id' => (int)$productId,
                'product_option_id' => $productOptionId,
                'option_id' => $optionId,
                'option_value_id' => (int)$optionValueId,
                'quantity' => 0,
                'subtract' => 1,
                'price' => 0,
                'price_prefix' => '+',
                'points' => 0,
                'points_prefix' => '+',
                'weight' => 0,
                'weight_prefix' => '+',
            ]);
        }
        return true;
    }

    /**
     * 删除optin和optionvalue记录
     *
     * @param int $productId
     * @param array $optionArr
     * @throws
     * @return bool
     */
    public function delOptionAndValueInfo(int $productId, array $optionArr)
    {
        if ($productId && $optionArr) {
            ProductOption::query()
                ->where('product_id', $productId)
                ->whereIn('option_id', $optionArr)
                ->delete();

            ProductOptionValue::query()
                ->where('product_id', $productId)
                ->whereIn('option_id', $optionArr)
                ->delete();
        }

        return true;
    }

    /**
     * 更新产品关联
     * @param int $productId
     * @param array $associateProductIds
     * @return array
     * @throws \Exception
     */
    public function updateProductAssociate(int $productId, array $associateProductIds = []): array
    {
        $existingProductAssociates = ProductAssociate::query()->where('product_id', $productId)->get();
        ProductAssociate::query()->where('product_id', $productId)->delete();

        if ($existingProductAssociates) {
            foreach ($existingProductAssociates as $existingProductAssociate) {
                /** @var ProductAssociate $existingProductAssociate */
                ProductAssociate::query()
                    ->where('product_id', $existingProductAssociate->associate_product_id)
                    ->where('associate_product_id', '!=', $existingProductAssociate->associate_product_id)
                    ->delete();
            }
        }

        if (empty($associateProductIds)) {
            ProductAssociate::query()
                ->where('product_id', $productId)
                ->where('associate_product_id', $productId)
                ->delete();

            ProductAssociate::query()
               ->insert([
                   'product_id' => $productId,
                   'associate_product_id' => $productId,
               ]);
        } else {
            foreach ($associateProductIds as $associateProductId) {
                $associateProductAssociateProductIds = ProductAssociate::query()->where('product_id', $associateProductId)->pluck('associate_product_id')->toArray();
                $associateProductIds = array_merge($associateProductIds, $associateProductAssociateProductIds);
            }

            array_push($associateProductIds, $productId);
            $associateProductIds = array_unique($associateProductIds);

            foreach ($associateProductIds as $associateProductId1) {
                foreach ($associateProductIds as $associateProductId2) {
                    ProductAssociate::query()
                        ->where('product_id', $associateProductId1)
                        ->where('associate_product_id', $associateProductId2)
                        ->delete();

                    ProductAssociate::query()
                        ->insert([
                            'product_id' => $associateProductId1,
                            'associate_product_id' => $associateProductId2,
                        ]);
                }
            }
        }

        return array_diff($associateProductIds, [$productId]);
    }
}
