<?php

namespace App\Models\Product;

use Framework\Model\EloquentModel;

/**
 * App\Models\Product\ProductAssociate
 *
 * @property int $id
 * @property int $product_id
 * @property int $associate_product_id 关联产品id
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\ProductAssociate newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\ProductAssociate newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\ProductAssociate query()
 * @mixin \Eloquent
 */
class ProductAssociate extends EloquentModel
{
    protected $table = 'oc_product_associate';

    protected $dates = [
        
    ];

    protected $fillable = [
        'product_id',
        'associate_product_id',
    ];
}
