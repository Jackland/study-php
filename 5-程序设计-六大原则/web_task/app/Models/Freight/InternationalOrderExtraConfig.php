<?php

namespace App\Models\Freight;

use Framework\Model\EloquentModel;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Freight\InternationalOrderExtraConfig
 *
 * @property int $id
 * @property int|null $country_id 国别id
 * @property string|null $max_length 最长边
 * @property string|null $max_length_rule 最长边规则
 * @property string|null $min_length 最小边
 * @property string|null $min_length_rule 最小边规则
 * @property string|null $volume_density 体积密度
 * @property string|null $volume_density_rule 体积密度规则
 * @property string|null $extra_charge 附加费用
 * @property string|null $memo 备注
 * @property \Illuminate\Support\Carbon $create_time 创建时间
 * @property string $create_username 创建人
 * @property \Illuminate\Support\Carbon|null $update_time 更新时间
 * @property string|null $update_username 更新人
 * @property string|null $program_code 版本号
 */
class InternationalOrderExtraConfig extends Model
{
    protected $table = 'tb_sys_international_order_extra_config';

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'country_id',
        'max_length',
        'max_length_rule',
        'min_length',
        'min_length_rule',
        'volume_density',
        'volume_density_rule',
        'extra_charge',
        'memo',
        'create_time',
        'create_username',
        'update_time',
        'update_username',
        'program_code',
    ];
}
