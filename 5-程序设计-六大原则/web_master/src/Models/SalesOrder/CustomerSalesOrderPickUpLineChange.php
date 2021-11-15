<?php

namespace App\Models\SalesOrder;

use Eloquent;
use Framework\Model\Eloquent\Builder;
use Framework\Model\EloquentModel;
use Illuminate\Support\Carbon;

/**
 * App\Models\SalesOrder\CustomerSalesOrderPickUpLineChange
 *
 * @property int $id
 * @property int $sales_order_id 销售单ID
 * @property string $origin_pick_up_json 原上门取货信息
 * @property string $store_pick_up_json 仓库给的上门取货信息
 * @property int $is_buyer_accept buyer 是否接受
 * @property int $is_notify_store buyer 接受后是否成功通知仓库
 * @property Carbon $create_time 创建时间
 * @property Carbon $update_time 更新时间
 * @method static Builder|CustomerSalesOrderPickUpLineChange newModelQuery()
 * @method static Builder|CustomerSalesOrderPickUpLineChange newQuery()
 * @method static Builder|CustomerSalesOrderPickUpLineChange query()
 * @mixin Eloquent
 * @property-read CustomerSalesOrderPickUp $pickUp
 * @property-read CustomerSalesOrder $salesOrder
 */
class CustomerSalesOrderPickUpLineChange extends EloquentModel
{
    protected $table = 'tb_sys_customer_sales_order_pick_up_line_change';

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'sales_order_id',
        'origin_pick_up_json',
        'store_pick_up_json',
        'is_buyer_accept',
        'is_notify_store',
        'create_time',
        'update_time',
    ];

    //销售单
    public function salesOrder()
    {
        return $this->belongsTo(CustomerSalesOrder::class, 'sales_order_id');
    }

    //销售单
    public function pickUp()
    {
        return $this->hasOne(CustomerSalesOrderPickUp::class, 'sales_order_id', 'sales_order_id');
    }
}
