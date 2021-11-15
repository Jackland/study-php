<?php

namespace App\Models\SalesOrder;

use Framework\Model\EloquentModel;

/**
 * App\Models\SalesOrder\CustomerSalesOrderCancel
 *
 * @property int $id 自增主键
 * @property int $reason 取消原因;1、订单取消发货 2、补买/错发取消订单
 * @property int $connection_relation 销采订单的关联关系 1、保留 0、解除 2、不操作
 * @property string $certificate 上传凭证
 * @property string $memo 备注
 * @property string|null $create_user_name 创建者
 * @property \Illuminate\Support\Carbon|null $create_time 创建时间
 * @property string|null $update_user_name 更新者
 * @property \Illuminate\Support\Carbon|null $update_time 更新时间
 * @property int $header_id 销售订单头表Id
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SalesOrder\CustomerSalesOrderCancel newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SalesOrder\CustomerSalesOrderCancel newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SalesOrder\CustomerSalesOrderCancel query()
 * @mixin \Eloquent
 */
class CustomerSalesOrderCancel extends EloquentModel
{
    protected $table = 'tb_sys_customer_sales_order_cancel';

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'reason',
        'connection_relation',
        'certificate',
        'memo',
        'create_user_name',
        'create_time',
        'update_user_name',
        'update_time',
        'header_id',
    ];
}
