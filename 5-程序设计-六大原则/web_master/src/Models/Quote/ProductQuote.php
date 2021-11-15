<?php

namespace App\Models\Quote;

use Framework\Model\EloquentModel;

/**
 * App\Models\Quote\ProductQuote
 *
 * @property int $id 主键ID
 * @property string|null $agreement_no 协议编号
 * @property int $customer_id buyer id
 * @property int $product_id 产品id
 * @property string $product_key
 * @property int $quantity 议价采购数量
 * @property string $message 信息
 * @property string $price 成交价
 * @property int $status 0->Applied 申请,\r\n1->Approved 同意,\r\n2->Rejected 拒绝,\r\n3->Sold 已购买,\r\n4->Time Out 超时关闭,\r\n5->Canceled 用户取消
 * @property \Illuminate\Support\Carbon $date_added
 * @property int $order_id 采购订单ID
 * @property string $amount 该明细的总折扣
 * @property \Illuminate\Support\Carbon $date_used
 * @property string $origin_price 原价
 * @property string $discount_price 折扣价
 * @property string $discount 折扣
 * @property \Illuminate\Support\Carbon $date_approved seller同意的时间
 * @property string $amount_price_per 每个商品的价格的折扣\r\n即：（discount_price-price)*欧洲展示价格比例
 * @property string $amount_service_fee_per 每个商品的服务费的折扣\r\n (discount_price-price)-amount_price_per\r\n总折扣-价格的折扣=服务费的折扣
 * @property \Illuminate\Support\Carbon $update_time
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Quote\ProductQuote newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Quote\ProductQuote newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Quote\ProductQuote query()
 * @mixin \Eloquent
 * @property float $agreement_price 议价的协议价
 */
class ProductQuote extends EloquentModel
{
    protected $table = 'oc_product_quote';

    protected $dates = [
        'date_added',
        'date_used',
        'date_approved',
        'update_time',
    ];

    protected $fillable = [
        'agreement_no',
        'customer_id',
        'product_id',
        'product_key',
        'quantity',
        'message',
        'price',
        'status',
        'date_added',
        'order_id',
        'amount',
        'date_used',
        'origin_price',
        'discount_price',
        'discount',
        'date_approved',
        'amount_price_per',
        'amount_service_fee_per',
        'update_time',
    ];
}
