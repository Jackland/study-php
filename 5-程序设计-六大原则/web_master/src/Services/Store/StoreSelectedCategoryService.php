<?php

namespace App\Services\Store;

use App\Models\Store\StoreSelectedCategory;
use Carbon\Carbon;

class StoreSelectedCategoryService
{
    /**
     * 处理类目信息，从event/product.php中摘出
     * @param int $productId
     * @param int $categoryId
     * @param int $customerId
     * @return bool|int
     */
    public function handleSelectedCategory(int $productId, int $categoryId, int $customerId)
    {
        if (!$productId || !$categoryId) {
            return true;
        }

        $selectedCategory = StoreSelectedCategory::query()
            ->where('customer_id', $customerId)
            ->where('category_id', $categoryId)
            ->first();

        if ($selectedCategory) {
            return StoreSelectedCategory::query()
                ->where('id', $selectedCategory->id)
                ->update([
                    'product_id' => $productId,
                    'update_num' => $selectedCategory->update_num + 1,
                    'update_time' => Carbon::now()
                ]);
        }

        return StoreSelectedCategory::query()->insert([
                'customer_id' => $customerId,
                'category_id' => $categoryId,
                'product_id' => $productId,
                'update_num' => 1,
                'create_time' => Carbon::now(),
                'update_time' => Carbon::now()
            ]
        );
    }

}
