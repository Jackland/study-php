<?php

namespace App\Models\Cart;

use Framework\Model\EloquentModel;

/**
 * App\Models\Cart
 *
 * @property int $cart_id
 * @property int $api_id
 * @property int $customer_id
 * @property string $session_id
 * @property int $product_id
 * @property int $recurring_id
 * @property string $option
 * @property int $quantity
 * @property int $type_id 交易类型，用于持久化购物车环境的交易类型，type_id字典值维护在oc_setting表,code:transaction_type
 * @property int $add_cart_type 加入购物车时的类型，0是默认或最优价，1是常规价加入,2是阶梯价加入
 * @property int|null $agreement_id 各个交易类型设计的协议记录主键ID，若为普通类别，值为null
 * @property int|null $delivery_type 发货类型
 * @property \Illuminate\Support\Carbon $date_added
 * @property int $sort_time 时间戳，专用于排序
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Cart\Cart newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Cart\Cart newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Cart\Cart query()
 * @mixin \Eloquent
 */
class Cart extends EloquentModel
{
    protected $table = 'oc_cart';
    protected $primaryKey = 'cart_id';

    protected $dates = [
        'date_added',
    ];

    protected $fillable = [
        'api_id',
        'customer_id',
        'session_id',
        'product_id',
        'recurring_id',
        'option',
        'quantity',
        'type_id',
        'add_cart_type',
        'agreement_id',
        'delivery_type',
        'date_added',
        'sort_time',
    ];
}
