<?php

namespace App\Models\Futures;

use App\Models\Order\Order;
use App\Models\Product\Product;
use Framework\Model\EloquentModel;

/**
 * \App\Models\Futures\FuturesMarginProcess
 *
 * @property int $id 自增主键
 * @property int $agreement_id oc_futures_margin_agreement.id
 * @property int $advance_product_id 保证金审批通过之后，生成的头款商品ID
 * @property int $advance_order_id buyer购买了头款商品产生的采购订单号
 * @property bool $process_status 保证金付款流程进度.1:审批通过，头款商品创建成功;2:头款商品购买完成，尾款商品创建成功;3:尾款商品支付分销中;4:所有尾款商品销售完成;
 * @property string $create_time 创建时间
 * @property string $update_time 更新时间
 * @property-read \App\Models\Order\Order $advanceOrder
 * @property-read \App\Models\Product\Product $advanceProduct
 * @property-read \App\Models\Futures\FuturesMarginAgreement $agreement
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Futures\FuturesMarginProcess newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Futures\FuturesMarginProcess newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Futures\FuturesMarginProcess query()
 * @mixin \Eloquent
 */
class FuturesMarginProcess extends EloquentModel
{
    protected $table = 'oc_futures_margin_process';

    public function agreement()
    {
        return $this->belongsTo(FuturesMarginAgreement::class, 'agreement_id');
    }

    public function advanceProduct()
    {
        return $this->belongsTo(Product::class, 'advance_product_id');
    }

    public function advanceOrder()
    {
        return $this->belongsTo(Order::class, 'advance_order_id');
    }
}
