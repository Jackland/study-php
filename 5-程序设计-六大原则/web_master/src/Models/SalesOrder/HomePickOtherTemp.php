<?php

namespace App\Models\SalesOrder;

use Framework\Model\EloquentModel;

/**
 * App\Models\SalesOrder\HomePickOtherTemp
 *
 * @property int $id 自增id
 * @property string $platform 平台名称
 * @property int|null $other_platform_id 0:default,1:amazon,2:wayfair,3:walmart,4:overstock,5:home depot,6:Lowe's,7:other
 * @property string $sales_order_id 订单号
 * @property string $item_code oc product 中的sku
 * @property int $quantity 数量
 * @property string $ship_method 运输类型
 * @property string $carrier 物流方式
 * @property string|null $warehouse_code 仓库名称
 * @property string|null $warehouse_name 仓库名称简写
 * @property string $ship_to_name ShipToName
 * @property string|null $ship_to_email
 * @property string $ship_to_phone ShipToPhone
 * @property string $ship_to_address ShipToAddressDetail
 * @property string|null $ship_to_address2
 * @property string $ship_to_city ShipToCity
 * @property string $ship_to_state ShipToState
 * @property string $ship_to_zip ShipToPostalCode
 * @property string $ship_to_country ShipToCountry
 * @property string|null $tracking_number
 * @property string|null $remark
 * @property int|null $buyer_id
 * @property string|null $run_id 导入时分秒时间 配合buyer_id unique
 * @property string|null $memo 备注
 * @property string|null $create_user_name 创建者
 * @property string $create_time 创建时间
 * @property string|null $update_user_name 更新者
 * @property string $update_time 更新时间
 * @property string $program_code 程序号
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SalesOrder\HomePickOtherTemp newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SalesOrder\HomePickOtherTemp newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SalesOrder\HomePickOtherTemp query()
 * @mixin \Eloquent
 * @property-read \App\Models\SalesOrder\CustomerSalesOrderLine $line
 * @property int|null $warehouse_id 仓库Id tb_warehouses id
 */
class HomePickOtherTemp extends EloquentModel
{
    protected $table = 'tb_sys_customer_sales_order_other_temp';

    protected $fillable = [
        'platform',
        'other_platform_id',
        'sales_order_id',
        'item_code',
        'quantity',
        'ship_method',
        'carrier',
        'warehouse_code',
        'warehouse_name',
        'ship_to_name',
        'ship_to_email',
        'ship_to_phone',
        'ship_to_address',
        'ship_to_address2',
        'ship_to_city',
        'ship_to_state',
        'ship_to_zip',
        'ship_to_country',
        'tracking_number',
        'remark',
        'buyer_id',
        'run_id',
        'memo',
        'create_user_name',
        'create_time',
        'update_user_name',
        'update_time',
        'program_code',
    ];

    public function line()
    {
        return $this->hasOne(CustomerSalesOrderLine::class, 'temp_id');
    }
}
