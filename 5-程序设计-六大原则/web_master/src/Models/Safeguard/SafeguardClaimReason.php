<?php

namespace App\Models\Safeguard;

use Framework\Model\EloquentModel;

/**
 * App\Models\Safeguard\SafeguardClaimReason
 *
 * @property int $id ID
 * @property int $config_type 理赔类型[1:退货服务，2:物流服务]
 * @property string $reason_zh 中文理由
 * @property string $reason_en 英文理由
 * @property int $is_deleted 是否删除[1:已删除，0:未删除]
 * @property string $operator_name 最后操作人
 * @property \Illuminate\Support\Carbon $create_time 创建时间
 * @property \Illuminate\Support\Carbon $update_time 更新时间
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Safeguard\SafeguardClaimReason newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Safeguard\SafeguardClaimReason newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Safeguard\SafeguardClaimReason query()
 * @mixin \Eloquent
 */
class SafeguardClaimReason extends EloquentModel
{
    protected $table = 'oc_safeguard_claim_reason';

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'config_type',
        'reason_zh',
        'reason_en',
        'is_deleted',
        'operator_name',
        'create_time',
        'update_time',
    ];
}
