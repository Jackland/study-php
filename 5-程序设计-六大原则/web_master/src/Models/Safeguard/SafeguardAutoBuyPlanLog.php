<?php

namespace App\Models\Safeguard;

use App\Models\Customer\Customer;
use Framework\Model\EloquentModel;

/**
 * App\Models\Safeguard\SafeguardAutoBuyPlanLog
 *
 * @property int $id 自增主键
 * @property int $plan_id oc_safeguard_auto_buy_plan.id
 * @property int $type 1为新建,２编辑,
 * @property string $content 日志内容
 * @property \Illuminate\Support\Carbon $create_time 创建时间
 * @property \Illuminate\Support\Carbon $update_time 更新时间
 * @property int $operator_id 操作人
 * @property-read \App\Models\Customer\Customer $customer
 * @property-read \App\Models\Safeguard\SafeguardAutoBuyPlan $plan
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Safeguard\SafeguardAutoBuyPlanLog newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Safeguard\SafeguardAutoBuyPlanLog newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Safeguard\SafeguardAutoBuyPlanLog query()
 * @mixin \Eloquent
 */
class SafeguardAutoBuyPlanLog extends EloquentModel
{
    protected $table = 'oc_safeguard_auto_buy_plan_log';

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'plan_id',
        'type',
        'content',
        'create_time',
        'update_time',
        'operator_id',
    ];

    public function plan()
    {
        return $this->belongsTo(SafeguardAutoBuyPlan::class, 'plan_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'operator_id');
    }
}
