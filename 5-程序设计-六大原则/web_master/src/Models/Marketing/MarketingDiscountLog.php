<?php

namespace App\Models\Marketing;

use Framework\Model\EloquentModel;

/**
 * App\Models\Marketing\MarketingDiscountLog
 *
 * @property int $id ID
 * @property int $discount_id oc_marketing_discount.id
 * @property int $type 1为新建,２编辑,
 * @property int $discount 折扣
 * @property string $product_scope 产品范围
 * @property string $buyer_scope buyer范围
 * @property \Illuminate\Support\Carbon $effective_time 生效时间
 * @property \Illuminate\Support\Carbon $expiration_time 失效时间
 * @property \Illuminate\Support\Carbon $create_time 创建时间
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Marketing\MarketingDiscountLog newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Marketing\MarketingDiscountLog newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Marketing\MarketingDiscountLog query()
 * @mixin \Eloquent
 */
class MarketingDiscountLog extends EloquentModel
{
    protected $table = 'oc_marketing_discount_log';

    protected $dates = [
        'effective_time',
        'expiration_time',
        'create_time',
    ];

    protected $fillable = [
        'discount_id',
        'type',
        'discount',
        'product_scope',
        'buyer_scope',
        'effective_time',
        'expiration_time',
        'create_time',
    ];
}
