<?php

namespace App\Models\Product\Option;

use Framework\Model\EloquentModel;

/**
 * App\Models\Product\Option\ProductOption
 *
 * @property int $product_option_id
 * @property int $product_id
 * @property int $option_id
 * @property string|null $value
 * @property int $required
 * @property string|null $VALUE
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\Option\ProductOption newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\Option\ProductOption newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\Option\ProductOption query()
 * @mixin \Eloquent
 */
class ProductOption extends EloquentModel
{
    protected $table = 'oc_product_option';
    protected $primaryKey = 'product_option_id';

    protected $dates = [

    ];

    protected $fillable = [
        'product_id',
        'option_id',
        'value',
        'required',
    ];
}
