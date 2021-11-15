<?php

namespace App\Models\Margin;

use App\Models\Order\Order;
use Framework\Model\EloquentModel;

/**
 * App\Models\Margin\MarginOrderRelation
 *
 * @property int $id 自增主键
 * @property int $margin_process_id tb_sys_margin_process.id
 * @property int $rest_order_id 尾款采购订单号
 * @property int $purchase_quantity 采购数量
 * @property string|null $memo 备注
 * @property string $create_time 创建时间
 * @property string $create_username 创建人
 * @property string|null $update_time 更新时间
 * @property string|null $update_username 更新人
 * @property string|null $program_code 版本号
 * @property int $product_id 尾款产品ID
 * @property-read \App\Models\Order\Order $order 所属订单
 * @property-read \App\Models\Margin\MarginProcess $process 所属协议进度
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Margin\MarginOrderRelation newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Margin\MarginOrderRelation newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Margin\MarginOrderRelation query()
 * @mixin \Eloquent
 */
class MarginOrderRelation extends EloquentModel
{
    protected $table = 'tb_sys_margin_order_relation';

    protected $fillable = [
        'margin_process_id',
        'rest_order_id',
        'purchase_quantity',
        'memo',
        'create_time',
        'create_username',
        'update_time',
        'update_username',
        'program_code',
        'product_id',
    ];

    public function process()
    {
        return $this->belongsTo(MarginProcess::class, 'margin_process_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'rest_order_id');
    }
}
