<?php

namespace App\Models\Margin;

use App\Models\Order\Order;
use App\Models\Product\Product;
use Framework\Model\EloquentModel;

/**
 * App\Models\Margin\MarginProcess
 *
 * @property int $id 自增主键
 * @property int $margin_id tb_sys_margin_agreement.id
 * @property string $margin_agreement_id tb_sys_margin_agreement.agreement_id
 * @property int $advance_product_id 保证金审批通过之后，生成的头款商品ID
 * @property int|null $advance_order_id buyer购买了头款商品产生的采购订单号
 * @property int|null $rest_product_id buyer购买完头款之后生成的尾款商品ID
 * @property int $process_status 保证金付款流程进度.1:审批通过，头款商品创建成功;2:头款商品购买完成，尾款商品创建成功;3:尾款商品支付分销中;4:所有尾款商品销售完成;
 * @property string|null $memo 备注
 * @property string $create_time 创建时间
 * @property string $create_username 创建人
 * @property string|null $update_time 更新时间
 * @property string|null $update_username 更新人
 * @property string|null $program_code 版本号
 * @property-read \App\Models\Order\Order|null $advanceOrder 协议头款订单
 * @property-read \App\Models\Product\Product $advanceProduct 协议头款产品
 * @property-read \App\Models\Product\Product $restProduct 协议尾款产品
 * @property-read \App\Models\Margin\MarginAgreement $agreement 所属协议
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Margin\MarginOrderRelation[] $relateOrders 所有的协议尾款订单
 * @property-read int|null $relate_orders_count
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Margin\MarginProcess newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Margin\MarginProcess newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Margin\MarginProcess query()
 * @mixin \Eloquent
 */
class MarginProcess extends EloquentModel
{
    protected $table = 'tb_sys_margin_process';

    protected $fillable = [
        'margin_id',
        'margin_agreement_id',
        'advance_product_id',
        'advance_order_id',
        'rest_product_id',
        'process_status',
        'memo',
        'create_time',
        'create_username',
        'update_time',
        'update_username',
        'program_code',
    ];

    /**
     *所属协议
     */
    public function agreement()
    {
        return $this->belongsTo(MarginAgreement::class, 'margin_id');
    }

    /**
     * 现货头款产品
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function advanceProduct()
    {
        return $this->belongsTo(Product::class, 'advance_product_id');
    }

    /**
     * 现货头款订单
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function advanceOrder()
    {
        return $this->belongsTo(Order::class, 'advance_order_id');
    }

    public function relateOrders()
    {
        return $this->hasMany(MarginOrderRelation::class, 'margin_process_id');
    }

    public function restProduct()
    {
        return $this->belongsTo(Product::class, 'rest_product_id');
    }
}
