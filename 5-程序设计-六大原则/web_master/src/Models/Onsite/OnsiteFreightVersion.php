<?php

namespace App\Models\Onsite;

use Framework\Model\EloquentModel;

/**
 * App\Models\Onsite\OnsiteFreightVersion
 *
 * @property int $id
 * @property int $seller_id oc_customer表主键
 * @property int $ltl_provide ltl报价提供状态，默认0 未提供 1已提供
 * @property \Illuminate\Support\Carbon $effect_start_time 生效时间
 * @property \Illuminate\Support\Carbon $effect_end_time 结束时间
 * @property string $tag 费用逻辑版本，v1 v2 v3 ...
 * @property \Illuminate\Support\Carbon|null $update_time 更新时间
 * @property int $status 状态，0=禁用 1=启用
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Onsite\OnsiteFreightVersion newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Onsite\OnsiteFreightVersion newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Onsite\OnsiteFreightVersion query()
 * @mixin \Eloquent
 */
class OnsiteFreightVersion extends EloquentModel
{
    protected $table = 'onsite_freight_version';

    protected $dates = [
        'effect_start_time',
        'effect_end_time',
        'update_time',
    ];

    protected $fillable = [
        'seller_id',
        'ltl_provide',
        'effect_start_time',
        'effect_end_time',
        'tag',
        'update_time',
        'status',
    ];
}
