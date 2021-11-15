<?php

namespace App\Models\Marketing;

use App\Enums\Marketing\MarketingDiscountProductType;
use Framework\Model\EloquentModel;

/**
 * App\Models\Marketing\MarketingDiscount
 *
 * @property int $id ID
 * @property int $seller_id seller_id
 * @property string $name 折扣名称
 * @property int $discount 折扣,0-100
 * @property int $product_scope 产品范围,-1所有产品
 * @property int $buyer_scope buyer范围，-1所有,1部分buyer
 * @property \Illuminate\Support\Carbon $effective_time 生效时间
 * @property \Illuminate\Support\Carbon $expiration_time 失效时间
 * @property int $is_del 是否删除,0未删除，1已删除
 * @property \Illuminate\Support\Carbon $create_time 创建时间
 * @property \Illuminate\Support\Carbon $update_time 更新时间
 * @property-read string $product_scope_name
 * @property-read int $discount_off
 * @property-read \App\Models\Buyer\Buyer $buyers
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Marketing\MarketingDiscount newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Marketing\MarketingDiscount newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Marketing\MarketingDiscount query()
 * @mixin \Eloquent
 */
class MarketingDiscount extends EloquentModel
{
    protected $table = 'oc_marketing_discount';
    protected $appends = ['product_scope_name', 'discount_off'];

    protected $dates = [
        'effective_time',
        'expiration_time',
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'seller_id',
        'discount',
        'product_scope',
        'buyer_scope',
        'effective_time',
        'expiration_time',
        'is_del',
        'create_time',
        'update_time',
    ];

    public function buyers()
    {
        return $this->hasMany(MarketingDiscountBuyer::class, 'discount_id','id');
    }

    public function getProductScopeNameAttribute()
    {
        return MarketingDiscountProductType::getDescription($this->attributes['product_scope'] ?? '');
    }

    public function getDiscountOffAttribute()
    {
        return max(100 - $this->attributes['discount'] ?? 100, 0);
    }

}
