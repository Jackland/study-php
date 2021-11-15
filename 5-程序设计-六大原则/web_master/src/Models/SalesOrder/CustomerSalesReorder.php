<?php

namespace App\Models\SalesOrder;

use App\Models\Rma\YzcRmaOrder;
use Framework\Model\EloquentModel;

/**
 * \App\Models\SalesOrder\CustomerSalesReorder
 *
 * @property int $id 自增主键
 * @property int $rma_id RMA ID
 * @property string $yzc_order_id 云资产订单ID
 * @property int $sales_order_id tb_sys_customer_sales_order.id
 * @property string $reorder_id 重发单订单ID
 * @property string $reorder_date 重发单日期
 * @property string|null $email 顾客邮箱地址
 * @property string $ship_name 收货人姓名
 * @property string $ship_address 收货地址
 * @property string $ship_city 收货城市
 * @property string $ship_state 收货州
 * @property string $ship_zip_code 收货邮编
 * @property string $ship_country 收货国家
 * @property string $ship_phone 收货人电话
 * @property string|null $ship_method 发货方式
 * @property string|null $ship_service_level 快递服务
 * @property string|null $ship_company 运输公司
 * @property string $store_name OMD店铺名称
 * @property int $store_id OMD店铺ID
 * @property int $buyer_id buyer_id
 * @property string $order_status 订单的状态
 * @property string|null $sell_manager 销售经理姓名
 * @property string|null $memo 备注
 * @property string|null $create_user_name 创建者
 * @property string|null $create_time 创建时间
 * @property string|null $update_user_name 修改者
 * @property string|null $update_time 修改时间
 * @property string|null $program_code 程序号
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\SalesOrder\CustomerSalesReorderLine[] $reorderLines
 * @property-read int|null $reorder_lines_count
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SalesOrder\CustomerSalesReorder newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SalesOrder\CustomerSalesReorder newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SalesOrder\CustomerSalesReorder query()
 * @mixin \Eloquent
 * @property-read \App\Models\Rma\YzcRmaOrder $rmaOrder
 * @property int|null $is_car_hire 约车状态 0 未约车 1已约车
 */
class CustomerSalesReorder extends EloquentModel
{
    protected $table = 'tb_sys_customer_sales_reorder';

    protected $appends = [
        'ship_address',
        'ship_name',
        'ship_phone',
        'ship_city',
    ];


    protected $fillable = [
        'rma_id',
        'yzc_order_id',
        'sales_order_id',
        'reorder_id',
        'reorder_date',
        'email',
        'ship_name',
        'ship_address',
        'ship_city',
        'ship_state',
        'ship_zip_code',
        'ship_country',
        'ship_phone',
        'ship_method',
        'ship_service_level',
        'ship_company',
        'store_name',
        'store_id',
        'buyer_id',
        'order_status',
        'sell_manager',
        'memo',
        'create_user_name',
        'create_time',
        'update_user_name',
        'update_time',
        'program_code',
    ];

    public function reorderLines()
    {
        return $this->hasMany(CustomerSalesReorderLine::class, 'reorder_header_id');
    }

    public function rmaOrder()
    {
        return $this->hasOne(YzcRmaOrder::class,'id','rma_id');
    }

    public function getEmailAttribute()
    {
        return app('db-aes')->decrypt($this->attributes['email'] ?? null);
    }

    public function getShipNameAttribute()
    {
        return app('db-aes')->decrypt($this->attributes['ship_name'] ?? null);
    }

    public function getShipCityAttribute()
    {
        return app('db-aes')->decrypt($this->attributes['ship_city'] ?? null);
    }

    public function getShipAddressAttribute()
    {
        return app('db-aes')->decrypt($this->attributes['ship_address'] ?? null);
    }

    public function getShipPhoneAttribute()
    {
        return app('db-aes')->decrypt($this->attributes['ship_phone'] ?? null);
    }
}
