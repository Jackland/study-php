<?php

namespace App\Models\Stock;

use Framework\Model\EloquentModel;

/**
 * App\Models\ReceiptsOrderHistory
 *
 * @property int $id 主键
 * @property int|null $parent_id 源记录主键
 * @property int $receive_order_id 入库单头表ID
 * @property string|null $update_content 修改字段
 * @property int|null $type 审核状态：1-创建入库单；2-修改待审核；3-审核通过；4-审核不通过；9-不需要审核
 * @property string|null $remark 备注
 * @property string|null $create_user_name 创建人
 * @property \Illuminate\Support\Carbon|null $create_time 创建时间
 * @property string|null $update_user_name 更新人
 * @property \Illuminate\Support\Carbon|null $update_time 更新时间
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Stock\ReceiptsOrderHistory newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Stock\ReceiptsOrderHistory newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Stock\ReceiptsOrderHistory query()
 * @mixin \Eloquent
 */
class ReceiptsOrderHistory extends EloquentModel
{
    protected $table = 'tb_sys_receipts_order_history';

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'parent_id',
        'receive_order_id',
        'update_content',
        'type',
        'remark',
        'create_user_name',
        'create_time',
        'update_user_name',
        'update_time',
    ];
}
