<?php

namespace App\Models\SalesOrder;

use Framework\Model\EloquentModel;

/**
 * App\Models\SalesOrder\CustomerSalesOrderFile
 *
 * @property int $id 自增id
 * @property int $order_id 销售订单表主键id
 * @property string|null $file_name 上传文件名称
 * @property string|null $file_path 上传文件路径
 * @property string|null $deal_file_name 上传文件名称
 * @property string|null $deal_file_path 上传文件路径
 * @property \Illuminate\Support\Carbon|null $order_date pick update date
 * @property string|null $carrier_name 物流名称
 * @property string|null $create_user_name 创建人
 * @property \Illuminate\Support\Carbon|null $create_time 创建时间
 * @property string|null $update_user_name
 * @property string|null $program_code 版本号
 * @property \Illuminate\Support\Carbon|null $update_time 更新时间
 * @property int|null $status
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SalesOrder\CustomerSalesOrderFile newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SalesOrder\CustomerSalesOrderFile newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SalesOrder\CustomerSalesOrderFile query()
 * @mixin \Eloquent
 */
class CustomerSalesOrderFile extends EloquentModel
{
    protected $table = 'tb_sys_customer_sales_order_file';

    protected $dates = [
        'order_date',
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'order_id',
        'file_name',
        'file_path',
        'deal_file_name',
        'deal_file_path',
        'order_date',
        'carrier_name',
        'create_user_name',
        'create_time',
        'update_user_name',
        'program_code',
        'update_time',
        'status',
    ];
}
