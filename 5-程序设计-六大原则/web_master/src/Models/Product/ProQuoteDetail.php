<?php

namespace App\Models\Product;

use Framework\Model\EloquentModel;

/**
 * App\Models\Product\ProQuoteDetail
 *
 * @property int $id
 * @property string|null $template_id 模板id
 * @property int $seller_id 用户id
 * @property int $product_id 商品id
 * @property int $min_quantity 最小数量
 * @property int $max_quantity 最大数量
 * @property string $price 价格
 * @property string|null $home_pick_up_price 上门取货价
 * @property int $sort_order 排序
 * @property \Illuminate\Support\Carbon|null $create_time 创建时间
 * @property \Illuminate\Support\Carbon|null $update_time 修改时间
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\ProQuoteDetail newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\ProQuoteDetail newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\ProQuoteDetail query()
 * @mixin \Eloquent
 */
class ProQuoteDetail extends EloquentModel
{
    protected $table = 'oc_wk_pro_quote_details';

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'template_id',
        'seller_id',
        'product_id',
        'min_quantity',
        'max_quantity',
        'price',
        'home_pick_up_price',
        'sort_order',
        'create_time',
        'update_time',
    ];
}
