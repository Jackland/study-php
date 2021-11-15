<?php

namespace App\Repositories\Product;

use App\Models\Link\ProductToCategory;

class ProductToCategoryRepository
{

    /**
     * 获取某个商品分类的最大category_id  新商品只有一个categorg_id了，随机取一条作为兼容处理，并向上推
     *
     * @param int $productId
     * @return array
     */
    public function getCategoryInfoByProductId(int $productId)
    {
        $categoryId = ProductToCategory::where('product_id', $productId)
            ->orderByDesc('category_id')
            ->value('category_id');
        if ($categoryId) {
            return app(CategoryRepository::class)->getUpperCategory($categoryId, [], false);
        }
        return [];
    }

}
