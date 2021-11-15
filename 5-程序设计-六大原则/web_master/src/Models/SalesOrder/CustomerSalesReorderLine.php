<?php

namespace App\Models\SalesOrder;

use Framework\Model\EloquentModel;

/**
 * \App\Models\SalesOrder\CustomerSalesReorderLine
 *
 * @property int $id 自增主键
 * @property int $reorder_header_id 重发订单头表ID
 * @property int $line_item_number 订单明细编号
 * @property string $product_name 产品名称
 * @property int $qty 销售数量
 * @property string $item_code ItemCode 对应oc_product mpn 或 sku
 * @property int $product_id oc_product.product_id
 * @property string|null $image_id 制造商ICON imageId
 * @property int $seller_id 卖家ID
 * @property string $item_status 订单明细的状态
 * @property string|null $is_exported 是否已同步，未同步为null，已同步为1
 * @property \Illuminate\Support\Carbon|null $exported_time 同步时间
 * @property string|null $is_synchroed 是否同步到在库系统：0 未同步，1 已同步
 * @property \Illuminate\Support\Carbon|null $synchroed_time 同步在库系统时间
 * @property string|null $omd_sync_id OMD订单同步ID主键记录
 * @property string|null $combo_info combo品的子combo信息记录，json格式
 * @property string|null $memo 备注
 * @property string|null $create_user_name 创建者
 * @property \Illuminate\Support\Carbon|null $create_time 创建时间
 * @property string|null $update_user_name 修改者
 * @property \Illuminate\Support\Carbon|null $update_time 修改时间
 * @property int $part_flag 重发类型1：发新品 2：发配件
 * @property string|null $program_code 程序号
 * @property string|null $osj_sync_flag 已complete订单明细同步在库系统标志：1-成功;0-失败
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SalesOrder\CustomerSalesReorderLine newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SalesOrder\CustomerSalesReorderLine newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SalesOrder\CustomerSalesReorderLine query()
 * @mixin \Eloquent
 */
class CustomerSalesReorderLine extends EloquentModel
{
    protected $table = 'tb_sys_customer_sales_reorder_line';

    protected $dates = [
        'exported_time',
        'synchroed_time',
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'reorder_header_id',
        'line_item_number',
        'product_name',
        'qty',
        'item_code',
        'product_id',
        'image_id',
        'seller_id',
        'item_status',
        'is_exported',
        'exported_time',
        'is_synchroed',
        'synchroed_time',
        'omd_sync_id',
        'combo_info',
        'memo',
        'create_user_name',
        'create_time',
        'update_user_name',
        'update_time',
        'part_flag',
        'program_code',
        'osj_sync_flag',
    ];
}
