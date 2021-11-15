<?php

namespace App\Models\SalesOrder;

use Framework\Model\EloquentModel;

/**
 * App\Models\SalesOrder\HomePickUploadFile
 *
 * @property int $id
 * @property string|null $container_id
 * @property string|null $order_id
 * @property string|null $file_name
 * @property string|null $file_path
 * @property int|null $status 0 为未启用
 * @property int|null $create_user_name
 * @property \Illuminate\Support\Carbon|null $create_time
 * @property string|null $deal_file_path
 * @property string|null $deal_file_name
 * @property string|null $size
 * @property string|null $tracking_number_img
 * @property string|null $order_id_img
 * @property string|null $run_id
 * @property string|null $weight_img
 * @property \Illuminate\Support\Carbon|null $update_time
 * @property int|null $update_user_name
 * @property string|null $tracking_number wayfair 订单下传回来的tracking_number
 * @property string|null $package_asn_img package_asn_img store_label专用
 * @property string|null $store_id_img store_id_img store_label专用
 * @property string|null $store_deal_file_path store_deal_file_path store_label专用
 * @property string|null $store_deal_file_name store_deal_file_name store_label专用
 * @property string|null $store_order_id_img store_order_id_img store_label专用
 * @property int|null $label_type 1 为label 2为Packing Slip
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SalesOrder\HomePickUploadFile newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SalesOrder\HomePickUploadFile newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SalesOrder\HomePickUploadFile query()
 * @mixin \Eloquent
 */
class HomePickUploadFile extends EloquentModel
{
    protected $table = 'tb_sys_customer_sales_dropship_upload_file';

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'container_id',
        'order_id',
        'file_name',
        'file_path',
        'status',
        'create_user_name',
        'create_time',
        'deal_file_path',
        'deal_file_name',
        'size',
        'tracking_number_img',
        'order_id_img',
        'run_id',
        'weight_img',
        'update_time',
        'update_user_name',
        'tracking_number',
        'package_asn_img',
        'store_id_img',
        'store_deal_file_path',
        'store_deal_file_name',
        'store_order_id_img',
        'label_type',
    ];
}
