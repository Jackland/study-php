<?php

namespace App\Models\SalesOrder;

use Framework\Model\EloquentModel;

/**
 * App\Models\SalesOrder\CustomerOrderModifyLog
 *
 * @property int $id 主键ID
 * @property int $process_code 操作码 1:修改发货信息,2:修改SKU,3:取消订单
 * @property int $status 操作状态 1:操作中,2:成功,3:失败
 * @property string $run_id 时间戳
 * @property string $fail_reason 失败原因，默认空串
 * @property string|null $before_record 修改前的状态记录
 * @property string|null $modified_record 修改后的状态记录
 * @property int $header_id 涉及的订单主键ID
 * @property string $order_id 涉及的订单ID
 * @property int|null $line_id 涉及的订单明细ID.订单整体操作类型，例如取消时可以为null
 * @property int $order_type 订单类型 1:普通订单,2:重发单
 * @property int $remove_bind 是否删除已存在的库存绑定关系 0:保留,1:删除
 * @property string $create_time 操作时间
 * @property string|null $update_time 结果更新时间
 * @property string|null $cancel_reason 取消订单的理由
 * @mixin \Eloquent
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SalesOrder\CustomerOrderModifyLog newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SalesOrder\CustomerOrderModifyLog newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SalesOrder\CustomerOrderModifyLog query()
 */
class CustomerOrderModifyLog extends EloquentModel
{
    protected $table = 'tb_sys_customer_order_modify_log';

    protected $fillable = [
        'process_code',
        'status',
        'run_id',
        'fail_reason',
        'before_record',
        'modified_record',
        'header_id',
        'order_id',
        'line_id',
        'order_type',
        'remove_bind',
        'create_time',
        'update_time',
        'cancel_reason',
    ];

    public function getBeforeRecordAttribute()
    {
        return app('db-aes')->decrypt($this->attributes['before_record'] ?? null);
    }

    public function getModifiedRecordAttribute()
    {
        return app('db-aes')->decrypt($this->attributes['modified_record'] ?? null);
    }
}
