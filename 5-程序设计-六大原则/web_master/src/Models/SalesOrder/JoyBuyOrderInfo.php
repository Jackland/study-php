<?php

namespace App\Models\SalesOrder;

use Framework\Model\EloquentModel;

/**
 * App\Models\SalesOrder\JoyBuyOrderInfo
 *
 * @property int $id
 * @property int $sales_order_id 销售订单表主键
 * @property int $joy_buy_order_id 京东订单id
 * @property int|null $order_status 京东订单状态
 * @property string|null $express_info 物流信息
 * @property int $operate_status 操作状态: 1 还需要继续查询 0 不需要继续查询
 * @property int $deal_track_status 物流处理状态: 1 需要执行出库 0已经处理出库
 * @property \Illuminate\Support\Carbon $create_time
 * @property \Illuminate\Support\Carbon $update_time
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SalesOrder\JoyBuyOrderInfo newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SalesOrder\JoyBuyOrderInfo newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SalesOrder\JoyBuyOrderInfo query()
 * @mixin \Eloquent
 * @property-read \App\Models\SalesOrder\CustomerSalesOrder $customerSalesOrder
 */
class JoyBuyOrderInfo extends EloquentModel
{
    protected $table = 'tb_joy_buy_order_info';

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'sales_order_id',
        'joy_buy_order_id',
        'order_status',
        'express_info',
        'operate_status',
        'deal_track_status',
        'create_time',
        'update_time',
    ];

    public function customerSalesOrder()
    {
        return $this->belongsTo(CustomerSalesOrder::class, 'sales_order_id');
    }
}
