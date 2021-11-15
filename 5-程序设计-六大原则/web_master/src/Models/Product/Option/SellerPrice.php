<?php

namespace App\Models\Product\Option;

use Framework\Model\EloquentModel;

/**
 * App\Models\Product\Option\SellerPrice
 *
 * @property int $id
 * @property int|null $product_id product主键
 * @property string|null $new_price 价格
 * @property \Illuminate\Support\Carbon|null $effect_time 生效日期
 * @property int|null $status 待更新 1, 更新成功 2, 失败 3
 * @property int $status_rate 待更新 1, 更新成功 2, 失败 3 ,更新到oc_product_price_rate表
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\Option\SellerPrice newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\Option\SellerPrice newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\Option\SellerPrice query()
 * @mixin \Eloquent
 */
class SellerPrice extends EloquentModel
{
    protected $table = 'oc_seller_price';

    protected $dates = [
        'effect_time',
    ];

    protected $fillable = [
        'product_id',
        'new_price',
        'effect_time',
        'status',
        'status_rate',
    ];
}
