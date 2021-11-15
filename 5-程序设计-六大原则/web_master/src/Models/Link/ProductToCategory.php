<?php

namespace App\Models\Link;

use App\Models\Product\Category;
use App\Models\Product\Product;
use Framework\Model\EloquentModel;

/**
 * App\Models\Link\ProductToCategory
 *
 * @property int $product_id
 * @property int $category_id
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Link\ProductToCategory newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Link\ProductToCategory newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Link\ProductToCategory query()
 * @mixin \Eloquent
 * @property-read \App\Models\Product\Category $category
 * @property-read \App\Models\Product\Product $product
 */
class ProductToCategory extends EloquentModel
{
    protected $table = 'oc_product_to_category';
    protected $primaryKey = ''; // 主键未知或大于1个

    protected $fillable = [

    ];

    public function product()
    {
        return $this->hasOne(Product::class, 'product_id', 'product_id');
    }

    public function category()
    {
        return $this->hasOne(Category::class, 'category_id', 'category_id');
    }
}
