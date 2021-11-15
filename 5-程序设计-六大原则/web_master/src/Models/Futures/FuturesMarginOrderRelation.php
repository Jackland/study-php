<?php

namespace App\Models\Futures;

use App\Models\Order\Order;
use App\Models\Product\Product;
use Framework\Model\EloquentModel;

/**
 * \App\Models\Futures\FuturesMarginOrderRelation
 *
 * @property int $id 自增主键
 * @property int $agreement_id oc_futures_margin_agreement.id
 * @property int $rest_order_id 尾款采购订单号
 * @property int $purchase_quantity 采购数量
 * @property int $product_id 尾款产品ID
 * @property string $create_time 创建时间
 * @property string $update_time 更新时间
 * @property-read \App\Models\Futures\FuturesMarginAgreement $agreement
 * @property-read \App\Models\Product\Product $product
 * @property-read \App\Models\Order\Order $restOrder
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Futures\FuturesMarginOrderRelation newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Futures\FuturesMarginOrderRelation newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Futures\FuturesMarginOrderRelation query()
 * @mixin \Eloquent
 */
class FuturesMarginOrderRelation extends EloquentModel
{
    protected $table = 'oc_futures_margin_order_relation';

    public function agreement()
    {
        return $this->belongsTo(FuturesMarginAgreement::class, 'agreement_id');
    }

    public function restOrder()
    {
        return $this->belongsTo(Order::class, 'rest_order_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_Id');
    }
}
