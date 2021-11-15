<?php

namespace App\Models\Delivery;

use App\Models\Order\Order;
use Framework\Model\EloquentModel;

/**
 * \App\Models\Delivery\ReceiveLine
 *
 * @property int $id 自增主键
 * @property int $receive_id ReceiveId tb_sys_receive.Id
 * @property int $buyer_id BuyerId tb_sys_receive.BuyerID
 * @property int|null $oc_order_id oc_order.order_id
 * @property int|null $oc_partner_order_id oc_customerpartner_to_order.id
 * @property string|null $source_code 来源说明
 * @property string|null $transaction_type 交易类型
 * @property int $product_id 产品ID
 * @property int $receive_qty 收货数量
 * @property float $unit_price 收货单价
 * @property float|null $tax 税
 * @property int|null $wh_id 仓库ID(预留字段)
 * @property int $seller_id seller_id
 * @property string|null $memo 备注
 * @property string|null $create_user_name 创建者
 * @property string|null $create_time 创建时间
 * @property string|null $update_user_name 更新者
 * @property string|null $update_time 更新时间
 * @property string|null $program_code 程序号
 * @property int|null $rma_id 退返品id
 * @property int|null $rma_product_id rma_product_id
 * @property-read \App\Models\Delivery\CostDetail $costDetail
 * @property-read \App\Models\Order\Order|null $order
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Delivery\ReceiveLine newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Delivery\ReceiveLine newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Delivery\ReceiveLine query()
 * @mixin \Eloquent
 */
class ReceiveLine extends EloquentModel
{
    protected $table = 'tb_sys_receive_line';

    public function costDetail()
    {
        return $this->hasOne(CostDetail::class, 'source_line_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'oc_order_id');
    }
}
