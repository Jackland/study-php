<?php

namespace App\Models\Safeguard;

use Framework\Model\EloquentModel;

/**
 * App\Models\Safeguard\SafeguardAutoBuyPlanDetail
 *
 * @property int $id 自增主键
 * @property int $plan_id oc_safeguard_auto_buy_plan.id
 * @property \Illuminate\Support\Carbon|null $effective_time 生效时间
 * @property \Illuminate\Support\Carbon|null $expiration_time 保障服务失效时间
 * @property string $safeguard_config_id oc_safeguard_config.rid,多个,分隔
 * @property \Illuminate\Support\Carbon $create_time 创建时间
 * @property \Illuminate\Support\Carbon $update_time 更新时间
 * @property-read \App\Models\Safeguard\SafeguardAutoBuyPlan $plan
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Safeguard\SafeguardAutoBuyPlanDetail newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Safeguard\SafeguardAutoBuyPlanDetail newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Safeguard\SafeguardAutoBuyPlanDetail query()
 * @mixin \Eloquent
 */
class SafeguardAutoBuyPlanDetail extends EloquentModel
{
    protected $table = 'oc_safeguard_auto_buy_plan_detail';

    protected $dates = [
        'effective_time',
        'expiration_time',
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'plan_id',
        'effective_time',
        'expiration_time',
        'safeguard_config_id',
        'create_time',
        'update_time',
    ];

    public function plan()
    {
        return $this->belongsTo(SafeguardAutoBuyPlan::class, 'plan_id');
    }
}
