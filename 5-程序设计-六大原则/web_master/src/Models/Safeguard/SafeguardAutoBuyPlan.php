<?php

namespace App\Models\Safeguard;

use Framework\Model\EloquentModel;

/**
 * App\Models\Safeguard\SafeguardAutoBuyPlan
 *
 * @property int $id 自增主键
 * @property int $buyer_id
 * @property int $status 1:生效,2:终止
 * @property \Illuminate\Support\Carbon $create_time 创建时间
 * @property \Illuminate\Support\Carbon $update_time 更新时间
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Safeguard\SafeguardAutoBuyPlanDetail[] $planDetails
 * @property-read int|null $plan_details_count
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Safeguard\SafeguardAutoBuyPlan newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Safeguard\SafeguardAutoBuyPlan newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Safeguard\SafeguardAutoBuyPlan query()
 * @mixin \Eloquent
 */
class SafeguardAutoBuyPlan extends EloquentModel
{
    protected $table = 'oc_safeguard_auto_buy_plan';

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'buyer_id',
        'status',
        'create_time',
        'update_time',
    ];

    public function planDetails()
    {
        return $this->hasMany(SafeguardAutoBuyPlanDetail::class, 'plan_id');
    }
}
