<?php

namespace App\Models\Marketing;

use App\Enums\Marketing\MarketingTimeLimitProductLogStatus;
use App\Models\Product\Product;
use Framework\Model\EloquentModel;

/**
 * App\Models\Marketing\MarketingTimeLimitProduct
 *
 * @property int $id ID
 * @property int $head_id oc_marketing_time_limit.id
 * @property int $product_id
 * @property string|null $price 创建活动时的公开价格
 * @property int $discount 折扣,0-100
 * @property int $origin_qty 产品参加活动库存
 * @property int $qty 产品活动剩余库存
 * @property int $status 1未释放库存,10已释放库存
 * @property-read \App\Models\Product\Product $productDetail
 * @property-read int $lockedQty
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Marketing\MarketingTimeLimitProduct newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Marketing\MarketingTimeLimitProduct newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Marketing\MarketingTimeLimitProduct query()
 * @mixin \Eloquent
 */
class MarketingTimeLimitProduct extends EloquentModel
{
    protected $table = 'oc_marketing_time_limit_product';

    protected $dates = [

    ];

    protected $fillable = [
        'head_id',
        'product_id',
        'price',
        'discount',
        'origin_qty',
        'qty',
        'status'
    ];

    public function timeLimit()
    {
        return $this->belongsTo(MarketingTimeLimit::class, 'head_id', 'id');
    }

    public function productDetail()
    {
        return $this->belongsTo(Product::class, 'product_id', 'product_id')
            ->select(['product_id', 'price', 'quantity', 'status', 'sku', 'buyer_flag']);
    }

    public function lockedQty()
    {
        return $this->hasMany(MarketingTimeLimitProductLog::class, 'head_id', 'id')
            ->where('status', MarketingTimeLimitProductLogStatus::LOCKED)->sum('qty');
    }
}
