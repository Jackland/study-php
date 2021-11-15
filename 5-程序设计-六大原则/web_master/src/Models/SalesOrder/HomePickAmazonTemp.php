<?php

namespace App\Models\SalesOrder;

use Framework\Model\EloquentModel;

/**
 * App\Models\SalesOrder\HomePickAmazonTemp
 *
 * @property int $id 自增id
 * @property string $order_id 订单ID OrderId
 * @property string $order_status 订单状态
 * @property string $warehouse_code 仓库代码
 * @property string|null $order_place_date 下单地日期
 * @property string|null $required_ship_date 要求到货日期
 * @property string|null $ship_method 运输方式
 * @property string|null $ship_method_code 运输方式编码
 * @property string|null $ship_to_name 送货地址
 * @property string|null $ship_to_address_line1 发货地址1
 * @property string|null $ship_to_address_line2 发货地址2
 * @property string|null $ship_to_address_line3 发货地址3
 * @property string|null $ship_to_state
 * @property string|null $ship_to_zip_code
 * @property string|null $ship_to_country
 * @property string|null $phone_number
 * @property string|null $is_gift
 * @property string|null $item_cost
 * @property string|null $sku B2B平台的sku
 * @property string|null $asin
 * @property string|null $item_title
 * @property string|null $item_quantity
 * @property string|null $tracking_id
 * @property string|null $shipped_date
 * @property string|null $memo 备注
 * @property int $create_id 创建人
 * @property string $create_time 创建时间
 * @property int|null $update_id 更新记录时间
 * @property string|null $update_time 更新时间
 * @property string $program_code 程序号
 * @property string|null $ship_to_city
 * @property int|null $buyer_id
 * @property string|null $gift_message
 * @property string|null $run_id 导入时分秒时间 配合buyer_id unique
 * @property string|null $warehouse_name
 * @property string|null $item_# 外部平台的sku
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SalesOrder\HomePickAmazonTemp newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SalesOrder\HomePickAmazonTemp newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SalesOrder\HomePickAmazonTemp query()
 * @mixin \Eloquent
 * @property-read \App\Models\SalesOrder\CustomerSalesOrderLine $line
 */
class HomePickAmazonTemp extends EloquentModel
{
    protected $table = 'tb_sys_customer_sales_dropship_temp';

    protected $fillable = [
        'order_id',
        'order_status',
        'warehouse_code',
        'order_place_date',
        'required_ship_date',
        'ship_method',
        'ship_method_code',
        'ship_to_name',
        'ship_to_address_line1',
        'ship_to_address_line2',
        'ship_to_address_line3',
        'ship_to_state',
        'ship_to_zip_code',
        'ship_to_country',
        'phone_number',
        'is_gift',
        'item_cost',
        'sku',
        'asin',
        'item_title',
        'item_quantity',
        'tracking_id',
        'shipped_date',
        'memo',
        'create_id',
        'create_time',
        'update_id',
        'update_time',
        'program_code',
        'ship_to_city',
        'buyer_id',
        'gift_message',
        'run_id',
        'warehouse_name',
        'item_#',
    ];

    public function line()
    {
        return $this->hasOne(CustomerSalesOrderLine::class, 'temp_id');
    }
}
