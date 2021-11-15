<?php

namespace App\Models\Product\Option;

use Framework\Model\EloquentModel;

/**
 * App\Models\SellerPriceHistory
 *
 * @property int $id
 * @property int|null $price_id seller_price主键
 * @property int|null $product_id product主键
 * @property string|null $price 历史价格
 * @property \Illuminate\Support\Carbon|null $add_date 插入时间
 * @property int|null $status 操作成功 1 失败 2
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\Option\SellerPriceHistory newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\Option\SellerPriceHistory newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\Option\SellerPriceHistory query()
 * @mixin \Eloquent
 */
class SellerPriceHistory extends EloquentModel
{
    protected $table = 'oc_seller_price_history';

    protected $dates = [
        'add_date',
    ];

    protected $fillable = [
        'price_id',
        'product_id',
        'price',
        'add_date',
        'status',
    ];
}
