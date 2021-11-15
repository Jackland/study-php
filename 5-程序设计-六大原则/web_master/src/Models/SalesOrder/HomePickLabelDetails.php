<?php

namespace App\Models\SalesOrder;

use Framework\Model\EloquentModel;

/**
 * App\Models\SalesOrder\HomePickLabelDetails
 *
 * @property int $id 自增id
 * @property int|null $line_id line表id 外键关联
 * @property int|null $temp_id dropship 临时表 id
 * @property int|null $order_id order表的id
 * @property int|null $is_combo 是否为combo
 * @property int|null $product_id 产品id
 * @property string|null $sku 产品sku
 * @property string|null $qty 产品数量
 * @property int|null $combo_sort
 * @property int|null $set_product_id 产品combo 映射
 * @property string|null $default_qty 映射产品 默认数量
 * @property int|null $line_item_number 排序数量
 * @property string|null $tracking_number 运单号
 * @property string|null $file_name 上传文件名
 * @property string|null $file_path 上传文件路径
 * @property string|null $deal_file_path 裁剪后的文件路径
 * @property string|null $deal_file_name 裁剪后的文件名
 * @property int|null $status 订单状态 1 正常 0 取消
 * @property string|null $create_user_name 创建人
 * @property string|null $create_time 创建时间
 * @property string|null $update_user_name
 * @property string|null $program_code 版本号
 * @property string|null $update_time 更新时间
 * @property string|null $run_id csv 上传文件的 标识 配合 create_user_name联合使用
 * @property string|null $tracking_number_img
 * @property string|null $order_id_img
 * @property string|null $weight_img
 * @property string|null $package_asn_img package_asn_img store_label专用
 * @property string|null $store_id_img store_id_img store_label专用
 * @property string|null $store_deal_file_path store_deal_file_path store_label专用
 * @property string|null $store_deal_file_name store_deal_file_name store_label专用
 * @property string|null $store_order_id_img store_order_id_img store_label专用
 * @property int|null $label_type 1 为label 2为Packing Slip
 * @property int|null $label_paper 0不是A4 1为A4
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SalesOrder\HomePickLabelDetails newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SalesOrder\HomePickLabelDetails newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SalesOrder\HomePickLabelDetails query()
 * @mixin \Eloquent
 * @property-read \App\Models\SalesOrder\CustomerSalesOrder $customerSalesOrder
 * @property-read \App\Models\SalesOrder\CustomerSalesOrderLine|null $lines
 */
class HomePickLabelDetails extends EloquentModel
{
    protected $table = 'tb_sys_customer_sales_dropship_file_details';

    protected $fillable = [
        'line_id',
        'temp_id',
        'order_id',
        'is_combo',
        'product_id',
        'sku',
        'qty',
        'combo_sort',
        'set_product_id',
        'default_qty',
        'line_item_number',
        'tracking_number',
        'file_name',
        'file_path',
        'deal_file_path',
        'deal_file_name',
        'status',
        'create_user_name',
        'create_time',
        'update_user_name',
        'program_code',
        'update_time',
        'run_id',
        'tracking_number_img',
        'order_id_img',
        'weight_img',
        'package_asn_img',
        'store_id_img',
        'store_deal_file_path',
        'store_deal_file_name',
        'store_order_id_img',
        'label_type',
        'label_paper',
    ];

    public function customerSalesOrder()
    {
        return $this->belongsTo(CustomerSalesOrder::class, 'id');
    }

    public function lines()
    {
        return $this->belongsTo(CustomerSalesOrderLine::class, 'line_id');
    }
}
