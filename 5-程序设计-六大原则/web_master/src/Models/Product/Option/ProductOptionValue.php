<?php

namespace App\Models\Product\Option;

use Framework\Model\EloquentModel;

/**
 * App\Models\Product\Option\ProductOptionValue
 *
 * @property int $product_option_value_id
 * @property int $product_option_id
 * @property int $product_id
 * @property int $option_id
 * @property int $option_value_id
 * @property int $quantity
 * @property int $subtract
 * @property string $price
 * @property string $price_prefix
 * @property int $points
 * @property string $points_prefix
 * @property string $weight
 * @property string $weight_prefix
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\Option\ProductOptionValue newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\Option\ProductOptionValue newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\Option\ProductOptionValue query()
 * @mixin \Eloquent
 * @property-read \App\Models\Product\Option\OptionValueDescription $optionValueDescription
 */
class ProductOptionValue extends EloquentModel
{
    protected $table = 'oc_product_option_value';
    protected $primaryKey = 'product_option_value_id';

    protected $dates = [
    ];

    protected $fillable = [
        'product_option_id',
        'product_id',
        'option_id',
        'option_value_id',
        'quantity',
        'subtract',
        'price',
        'price_prefix',
        'points',
        'points_prefix',
        'weight',
        'weight_prefix',
    ];

    public function optionValueDescription()
    {
        return $this->hasOne(OptionValueDescription::class, 'option_value_id', 'option_value_id');
    }
}
