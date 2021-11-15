<?php

namespace App\Services\Product;

use App\Models\Link\ProductToTag;
use Carbon\Carbon;

class ProductToTagService
{
    /**
     * 插入tag标签
     *
     * @param int $productId
     * @param int $tagId
     * @return mixed
     */
    public function insertProductTag($productId, $tagId)
    {
        if ($productId && $tagId) {
            return ProductToTag::query()->firstOrCreate(['product_id' => $productId, 'tag_id' => $tagId],
                [
                    'is_sync_tag' => 0,
                    'create_user_name' => 'system',
                    'create_time' => Carbon::now(),
                    'update_user_name' => NULL,
                    'update_time' => NULL,
                    'program_code' => 'add product',
                ]);
        }
        return true;
    }

}
