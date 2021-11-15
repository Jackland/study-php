<?php

namespace App\Models\SalesOrder;

use App\Models\Link\CustomerPartnerToProduct;
use App\Models\Link\OrderAssociated;
use App\Models\Product\Product;
use Framework\Model\EloquentModel;

/**
 * App\Models\SalesOrder\CustomerSalesOrderLine
 *
 * @property int $id 自增主键
 * @property int $temp_id 临时表ID(csv文件导入时temp表主键)
 * @property int $header_id 订单头表ID(Order表主键)
 * @property int $line_item_number 订单明细编号(明细在同一订单中的编号)
 * @property string $product_name 产品名称
 * @property int $qty 销售数量
 * @property string|null $item_price 销售单价
 * @property string|null $item_unit_discount 单个产品折扣
 * @property string|null $item_tax 单个产品税费
 * @property string|null $item_code ItemCode 对应oc_product mpn 或 sku
 * @property int|null $product_id 发货SkuId(oc_product.product_id)
 * @property string|null $alt_item_id AsinCode
 * @property string|null $ship_amount 运费
 * @property string|null $image_id 制造商ICON imageid
 * @property string|null $line_comments 订单明细的备注
 * @property int|null $seller_id 卖家ID
 * @property string $run_id 导入时分秒时间
 * @property string $item_status 订单明细的状态
 * @property string|null $memo 备注
 * @property string|null $is_exported 是否已同步，未同步为null，已同步为1
 * @property string|null $exported_time 同步时间
 * @property string|null $create_user_name 创建者
 * @property string|null $create_time 创建时间
 * @property string|null $update_user_name 更新者
 * @property string|null $update_time 更新时间
 * @property string|null $program_code 程序号
 * @property string|null $is_synchroed \r\n是否同步到在库系统：0 未同步，1 已同步  2 同步失败
 * @property string|null $synchroed_time 同步在库时间
 * @property string|null $omd_sync_id OMD订单同步ID主键记录
 * @property string|null $combo_info combo品的子combo信息记录，json格式
 * @property int|null $part_flag 是否为配件0：不是1：是
 * @property int|null $osj_sync_flag 已complete订单明细同步在库系统标志：1-成功;0-失败
 * @property int|null $whId 取自自动购买订单临时表的whId字段，表示订单在OMD或者在库系统的指定的发货仓库ID
 * @property string|null $platform_sku 平台SKU
 * @property int|null $sales_person_id 销售人员id
 * @property int|null $sales_agent_storage_qty 确认收货数量，初始为null
 * @property-read \App\Models\SalesOrder\CustomerSalesOrder $customerSalesOrder
 * @property-read \App\Models\SalesOrder\HomePickWayfairTemp $wayfairTemp
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Link\OrderAssociated[] $orderAssociates
 * @property-read int|null $order_associates_count
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SalesOrder\CustomerSalesOrderLine newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SalesOrder\CustomerSalesOrderLine newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SalesOrder\CustomerSalesOrderLine query()
 * @mixin \Eloquent
 * @property string|null $label_combo_info 上门取货产品的label上传成功后记录，用于对比combo info
 * @property int|null $sourceltem_number 平台原始明细ID
 * @property float|null $estimate_freight 单件产品预估运费（包含打包费），总的需要乘数量
 * @property-read \App\Models\Product\Product|null $product
 */
class CustomerSalesOrderLine extends EloquentModel
{
    protected $table = 'tb_sys_customer_sales_order_line';

    protected $fillable = [
        'temp_id',
        'header_id',
        'line_item_number',
        'product_name',
        'qty',
        'item_price',
        'item_unit_discount',
        'item_tax',
        'item_code',
        'product_id',
        'alt_item_id',
        'ship_amount',
        'image_id',
        'line_comments',
        'seller_id',
        'run_id',
        'item_status',
        'memo',
        'is_exported',
        'exported_time',
        'create_user_name',
        'create_time',
        'update_user_name',
        'update_time',
        'program_code',
        'is_synchroed',
        'synchroed_time',
        'omd_sync_id',
        'combo_info',
        'part_flag',
        'osj_sync_flag',
        'whId',
        'platform_sku',
        'sales_person_id',
        'sales_agent_storage_qty',
    ];

    public function customerSalesOrder()
    {
        return $this->belongsTo(CustomerSalesOrder::class, 'header_id');
    }

    public function orderAssociates()
    {
        return $this->hasMany(OrderAssociated::class, 'sales_order_line_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class,'item_code','sku');
    }

    public function wayfairTemp()
    {
        return $this->belongsTo(HomePickWayfairTemp::class,'temp_id');
    }
}
