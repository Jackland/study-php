<?php

namespace App\Models\Product\Option;

use Framework\Model\EloquentModel;

/**
 * App\Models\Product\Option\ProductImage
 *
 * @property int $product_image_id
 * @property int $product_id
 * @property string|null $image
 * @property int $sort_order
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\Option\ProductImage newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\Option\ProductImage newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\Option\ProductImage query()
 * @mixin \Eloquent
 */
class ProductImage extends EloquentModel
{
    protected $table = 'oc_product_image';
    protected $primaryKey = 'product_image_id';

    protected $dates = [
        
    ];

    protected $fillable = [
        'product_id',
        'image',
        'sort_order',
    ];
}
