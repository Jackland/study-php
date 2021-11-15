<?php

namespace App\Models\Marketing;

use Framework\Model\EloquentModel;

/**
 * App\Models\Marketing\MarketingTimeLimitProductLog
 *
 * @property int $id ID
 * @property int $head_id oc_marketing_time_limit_product.id
 * @property int $product_id
 * @property int|null $order_id oc_order.order_id
 * @property int|null $order_product_id oc_order_product.id
 * @property int|null $transaction_type 交易类型
 * @property string|null $price 下单价格
 * @property int $qty 数量
 * @property int $status 10创建增加活动库存,15补充活动动库存,20废弃,30锁定库存
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Marketing\MarketingTimeLimitProductLog newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Marketing\MarketingTimeLimitProductLog newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Marketing\MarketingTimeLimitProductLog query()
 * @mixin \Eloquent
 */
class MarketingTimeLimitProductLog extends EloquentModel
{
    protected $table = 'oc_marketing_time_limit_product_log';

    protected $dates = [

    ];

    protected $fillable = [
        'head_id',
        'product_id',
        'order_id',
        'order_product_id',
        'transaction_type',
        'price',
        'qty',
        'status',
    ];
}
