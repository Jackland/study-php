<?php

namespace App\Models\SalesOrder;

use Framework\Model\EloquentModel;

/**
 * App\Models\SalesOrder\HomePickWayfairTemp
 *
 * @property int $id 自增id
 * @property string $warehouse_name 发货仓库名称
 * @property string|null $store_name SalesPlatform
 * @property string $order_id order_id
 * @property string|null $po_date
 * @property string|null $must_ship_by
 * @property string|null $backorder_date
 * @property string|null $order_status
 * @property string|null $item_code B2BItemCode
 * @property string|null $item_name
 * @property string $quantity ShipToQty
 * @property string|null $wholesale_price BuyerSkuCommercialValue
 * @property string|null $ship_method ShipToService
 * @property string|null $carrier_name
 * @property string|null $shipping_account_number 只保存在数据库，不在任何地方展示
 * @property string $ship_to_name ShipToName
 * @property string $ship_to_address ShipToAddressDetail
 * @property string|null $ship_to_address2
 * @property string $ship_to_city ShipToCity
 * @property string $ship_to_state ShipToState
 * @property string $ship_to_zip ShipToPostalCode
 * @property string $ship_to_phone ShipToPhone
 * @property string|null $inventory_at_po_time
 * @property string|null $inventory_send_date
 * @property string|null $ship_speed
 * @property string|null $po_date_&_time
 * @property string|null $registered_timestamp
 * @property string|null $customization_text
 * @property string|null $event_name
 * @property string|null $event_id
 * @property string|null $event_start_date
 * @property string|null $event_end_date
 * @property string|null $event_type
 * @property string|null $backorder_reason
 * @property string|null $original_product_id
 * @property string|null $original_product_name
 * @property string|null $event_inventory_source
 * @property string|null $packing_slip_url
 * @property string|null $tracking_#
 * @property string|null $ready_for_pickup_date
 * @property string|null $sku
 * @property string $destination_country ShipToCountry
 * @property string|null $depot_id
 * @property string|null $depot_name
 * @property string|null $wholesale_event_source
 * @property string|null $wholesale_event_store_source
 * @property string|null $b2border
 * @property string|null $composite_wood_product
 * @property string|null $memo 备注
 * @property int $create_id 创建人
 * @property string $create_time 创建时间
 * @property int|null $update_id 更新记录时间
 * @property string|null $update_time 更新时间
 * @property string $program_code 程序号
 * @property int|null $buyer_id
 * @property string|null $run_id 导入时分秒时间 配合buyer_id unique
 * @property string|null $warehouse_code
 * @property string|null $item_#
 * @property string|null $sales_channel sales_channel 欧洲wayfair专有
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SalesOrder\HomePickWayfairTemp newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SalesOrder\HomePickWayfairTemp newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SalesOrder\HomePickWayfairTemp query()
 * @mixin \Eloquent
 * @property-read \App\Models\SalesOrder\CustomerSalesOrderLine $line
 */
class HomePickWayfairTemp extends EloquentModel
{
    protected $table = 'tb_sys_customer_sales_wayfair_temp';

    protected $fillable = [
        'warehouse_name',
        'store_name',
        'order_id',
        'po_date',
        'must_ship_by',
        'backorder_date',
        'order_status',
        'item_code',
        'item_name',
        'quantity',
        'wholesale_price',
        'ship_method',
        'carrier_name',
        'shipping_account_number',
        'ship_to_name',
        'ship_to_address',
        'ship_to_address2',
        'ship_to_city',
        'ship_to_state',
        'ship_to_zip',
        'ship_to_phone',
        'inventory_at_po_time',
        'inventory_send_date',
        'ship_speed',
        'po_date_&_time',
        'registered_timestamp',
        'customization_text',
        'event_name',
        'event_id',
        'event_start_date',
        'event_end_date',
        'event_type',
        'backorder_reason',
        'original_product_id',
        'original_product_name',
        'event_inventory_source',
        'packing_slip_url',
        'tracking_#',
        'ready_for_pickup_date',
        'sku',
        'destination_country',
        'depot_id',
        'depot_name',
        'wholesale_event_source',
        'wholesale_event_store_source',
        'b2border',
        'composite_wood_product',
        'memo',
        'create_id',
        'create_time',
        'update_id',
        'update_time',
        'program_code',
        'buyer_id',
        'run_id',
        'warehouse_code',
        'item_#',
        'sales_channel',
    ];

    public function line()
    {
        return $this->hasOne(CustomerSalesOrderLine::class, 'temp_id');
    }
}
