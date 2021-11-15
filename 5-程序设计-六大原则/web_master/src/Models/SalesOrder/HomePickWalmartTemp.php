<?php

namespace App\Models\SalesOrder;

use Framework\Model\EloquentModel;

/**
 * App\Models\SalesOrder\HomePickWalmartTemp
 *
 * @property int $id
 * @property int $buyer_id
 * @property int $run_id 导入时分秒时间 配合buyer_id unique
 * @property string $order_id PO#
 * @property string|null $order Order#
 * @property string|null $order_date Order Date
 * @property string|null $ship_by Ship By
 * @property string $ship_to_name Customer Name
 * @property string|null $ship_to_address
 * @property string $ship_to_phone B2BItemCode
 * @property int $store_id
 * @property string $ship_to_address1 Ship to Address 1
 * @property string|null $ship_to_address2 Ship to Address 2
 * @property string $city
 * @property string $state
 * @property string $zip
 * @property string|null $flids FLIDS
 * @property string $ship_node walmart仓库
 * @property string|null $warehouse_code 仓库code
 * @property int $line
 * @property string $upc
 * @property string $platform_sku walmart的sku
 * @property string $item_code b2b的sku
 * @property string|null $status
 * @property string|null $item_description
 * @property int $qty
 * @property string $ship_to
 * @property string|null $shipping_method
 * @property string|null $requested_carrier_method Requested Carrier Method
 * @property string|null $carrier
 * @property string|null $update_status
 * @property string|null $update_qty
 * @property string|null $tracking_number Tracking Number
 * @property string $package_asn Package ASN
 * @property string $create_time 创建时间
 * @property string $update_time 更新时间
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SalesOrder\HomePickWalmartTemp newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SalesOrder\HomePickWalmartTemp newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SalesOrder\HomePickWalmartTemp query()
 * @mixin \Eloquent
 */
class HomePickWalmartTemp extends EloquentModel
{
    protected $table = 'tb_sys_customer_sales_walmart_temp';

    protected $fillable = [
        'buyer_id',
        'run_id',
        'order_id',
        'order',
        'order_date',
        'ship_by',
        'ship_to_name',
        'ship_to_address',
        'ship_to_phone',
        'store_id',
        'ship_to_address1',
        'ship_to_address2',
        'city',
        'state',
        'zip',
        'flids',
        'ship_node',
        'warehouse_code',
        'line',
        'upc',
        'platform_sku',
        'item_code',
        'status',
        'item_description',
        'qty',
        'ship_to',
        'shipping_method',
        'requested_carrier_method',
        'carrier',
        'update_status',
        'update_qty',
        'tracking_number',
        'package_asn',
        'create_time',
        'update_time',
    ];

    public function line()
    {
        return $this->hasOne(CustomerSalesOrderLine::class, 'temp_id');
    }
}
