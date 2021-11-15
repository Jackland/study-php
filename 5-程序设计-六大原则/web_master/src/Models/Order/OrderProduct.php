<?php

namespace App\Models\Order;

use App\Models\Link\CustomerPartnerToProduct;
use App\Models\Link\OrderAssociated;
use App\Models\Product\Product;
use Framework\Model\EloquentModel;

/**
 * App\Models\Order\OrderProduct
 *
 * @property int $order_product_id
 * @property int $order_id
 * @property int $product_id
 * @property string $name
 * @property string $model
 * @property int $quantity
 * @property string $price
 * @property string $total
 * @property string $tax
 * @property int $reward
 * @property string $service_fee
 * @property string $poundage
 * @property string|null $service_fee_per
 * @property string $freight_per 单件运费（基础运费+超重附加费）
 * @property string|null $base_freight 基础运费
 * @property string|null $overweight_surcharge 超重附加费
 * @property string|null $freight_difference_per 运费差值
 * @property string|null $package_fee 打包费
 * @property string $coupon_amount 优惠券折扣
 * @property string $campaign_amount 活动满减金额
 * @property int $type_id 交易类型，用于持久化购物车环境的交易类型，type_id字典值维护在oc_setting表,code:transaction_type
 * @property int|null $agreement_id 各个交易类型设计的协议记录主键ID，若为普通类别，值为null
 * @property-read \App\Models\Order\Order $order
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Link\OrderAssociated[] $orderAssociates
 * @property-read int|null $order_associates_count
 * @property-read \App\Models\Product\Product $product
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Order\OrderProduct newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Order\OrderProduct newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Order\OrderProduct query()
 * @mixin \Eloquent
 * @property bool|null $is_sync 是否同步至在库系统 0 未同步  1 同步
 * @property-read \App\Models\Link\CustomerPartnerToProduct $customerPartnerToProduct
 * @property bool|null $discount 折扣,0-100,price是打折后的价格
 * @property int $danger_flag 商品危险品标识 0:非危险品; 1:危险品
 * @property float|null $discount_price 每个产品的折扣金额
 * @property bool $is_pure_logistics 是否纯物流订单
 */
class OrderProduct extends EloquentModel
{
    protected $table = 'oc_order_product';
    protected $primaryKey = 'order_product_id';

    protected $fillable = [
        'order_id',
        'product_id',
        'name',
        'model',
        'quantity',
        'price',
        'total',
        'tax',
        'reward',
        'service_fee',
        'poundage',
        'service_fee_per',
        'freight_per',
        'base_freight',
        'overweight_surcharge',
        'freight_difference_per',
        'package_fee',
        'type_id',
        'agreement_id',
        'danger_flag',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'order_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'product_id');
    }

    public function orderAssociates()
    {
        return $this->hasMany(OrderAssociated::class, 'order_product_id');
    }

    public function customerPartnerToProduct()
    {
        return $this->hasOne(CustomerPartnerToProduct::class, 'product_id', 'product_id');
    }
}
