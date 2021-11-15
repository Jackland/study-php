<?php

namespace App\Models\SalesOrder;

use Framework\Model\EloquentModel;

/**
 * App\Models\SalesOrder\CustomerSalesOrderTemp
 *
 * @property int $id 自增主键
 * @property string|null $orders_from 销售渠道 OrdersFrom
 * @property string $order_id 订单ID OrderId
 * @property string $line_item_number 订单明细ID LineItemNumber
 * @property string|null $email 邮箱地址 Email
 * @property string $order_date 订单日期 OrderDate
 * @property string|null $bill_name 付款人
 * @property string|null $bill_address 付款人地址
 * @property string|null $bill_city 付款人城市
 * @property string|null $bill_state 付款人州
 * @property string|null $bill_zip_code 付款人邮编
 * @property string|null $bill_country 付款人国家
 * @property string $ship_name 收货人姓名
 * @property string $ship_address1 收货地址1
 * @property string|null $ship_address2 收货地址2
 * @property string $ship_city 收货城市
 * @property string $ship_state 收货州
 * @property string $ship_zip_code 收货邮编
 * @property string $ship_country 收货国家
 * @property string|null $ship_phone 收货人电话
 * @property string $item_code ItemCode 对应oc_product mpn 或 sku
 * @property string|null $alt_item_id AltItemID asin 不代表业务逻辑
 * @property string $product_name 产品名称 Description
 * @property int $qty 销售数量 Qty
 * @property string|null $item_price 销售单价 ItemPrice
 * @property string|null $item_unit_discount 单个产品折扣
 * @property string|null $item_tax 单个产品税费
 * @property string|null $discount_amount 总折扣
 * @property string|null $tax_amount 总税费
 * @property string|null $ship_amount 核算运费
 * @property string|null $order_total 订单总价
 * @property string|null $payment_method 支付方式
 * @property string|null $ship_company 运输公司
 * @property string|null $ship_method 运输方式
 * @property string|null $ship_service_level 运输服务等级
 * @property string|null $brand_id 制造商id
 * @property string|null $customer_comments 顾客的备注
 * @property int|null $seller_id Seller编号
 * @property int $buyer_id BuyerID
 * @property string $run_id 导入时分秒时间
 * @property string|null $memo 备注
 * @property string|null $create_user_name 创建者
 * @property string|null $create_time 创建时间
 * @property string|null $update_user_name 更新者
 * @property string|null $update_time 更新时间
 * @property string|null $program_code 程序号
 * @property string|null $shipped_date 希望发货日期
 * @property string|null $ship_to_attachment_url 发货附件链接地址
 * @property string|null $omd_sync_id OMD订单同步ID主键记录
 * @property string|null $sell_manager 销售经理姓名
 * @property int|null $whId 自动购买的部分订单会指定特定仓库发货
 * @property string|null $external_store_name 自动购买订单回传的店铺名称，店铺ID目前存在此表的buyerId字段
 * @property string|null $amazon_sync_id Amazon-API订单同步ID主键记录
 * @property string|null $platform_sku 平台SKU
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SalesOrder\CustomerSalesOrderTemp newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SalesOrder\CustomerSalesOrderTemp newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SalesOrder\CustomerSalesOrderTemp query()
 * @mixin \Eloquent
 * @property-read \App\Models\SalesOrder\CustomerSalesOrderLine $line
 * @property bool|null $delivery_to_fba 是否送货到FBA仓库(欧洲以及日本FBA送仓) 0:否,1:是
 * @property string|null $bill_state_name 付款人州name
 * @property string|null $ship_state_name 收货州name
 */
class CustomerSalesOrderTemp extends EloquentModel
{
    protected $table = 'tb_sys_customer_sales_order_temp';

    protected $fillable = [
        'orders_from',
        'order_id',
        'line_item_number',
        'email',
        'order_date',
        'bill_name',
        'bill_address',
        'bill_city',
        'bill_state',
        'bill_zip_code',
        'bill_country',
        'ship_name',
        'ship_address1',
        'ship_address2',
        'ship_city',
        'ship_state',
        'ship_zip_code',
        'ship_country',
        'ship_phone',
        'item_code',
        'alt_item_id',
        'product_name',
        'qty',
        'item_price',
        'item_unit_discount',
        'item_tax',
        'discount_amount',
        'tax_amount',
        'ship_amount',
        'order_total',
        'payment_method',
        'ship_company',
        'ship_method',
        'ship_service_level',
        'brand_id',
        'customer_comments',
        'seller_id',
        'buyer_id',
        'run_id',
        'memo',
        'create_user_name',
        'create_time',
        'update_user_name',
        'update_time',
        'program_code',
        'shipped_date',
        'ship_to_attachment_url',
        'omd_sync_id',
        'sell_manager',
        'whId',
        'external_store_name',
        'amazon_sync_id',
        'platform_sku',
    ];

    public function line()
    {
        return $this->hasOne(CustomerSalesOrderLine::class, 'temp_id');
    }
}
