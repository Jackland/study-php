<?php

namespace App\Models\Safeguard;

use Framework\Model\EloquentModel;

/**
 * App\Models\Safeguard\SafeguardBuyerGroup
 *
 * @property int $id
 * @property string|null $buyer_group_name buyer分组名称
 * @property string|null $buyer_ids buyer分组对应成员id 例："1,2,3"
 * @property int|null $is_deleted 软删除，是否删除，1是，0否
 * @property string|null $create_user_name 创建者
 * @property \Illuminate\Support\Carbon|null $create_time 创建时间
 * @property string|null $update_user_name 更新者
 * @property \Illuminate\Support\Carbon|null $update_time 修改时间
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Safeguard\SafeguardBuyerGroup newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Safeguard\SafeguardBuyerGroup newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Safeguard\SafeguardBuyerGroup query()
 * @mixin \Eloquent
 */
class SafeguardBuyerGroup extends EloquentModel
{
    protected $table = 'oc_safeguard_buyer_group';

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'buyer_group_name',
        'buyer_ids',
        'is_deleted',
        'create_user_name',
        'create_time',
        'update_user_name',
        'update_time',
    ];
}
