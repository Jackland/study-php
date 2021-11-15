<?php

namespace App\Models\Freight;

use Framework\Model\EloquentModel;

/**
 * App\Models\Freight\InternationalOrderConfig
 *
 * @property int $id
 * @property int|null $country_id 国别id
 * @property string|null $extra_charge_ratio 附加费比例
 * @property string|null $vat_ratio vat比例
 * @property string|null $max_length 最长边
 * @property string|null $max_length_rule 最长边规则
 * @property string|null $second_length 次长边
 * @property string|null $second_length_rule 次长边规则
 * @property string|null $min_length 最小边
 * @property string|null $min_length_rule 最小边规则
 * @property string|null $girth 围长
 * @property string|null $girth_rule 围长规则
 * @property string|null $weight 重量
 * @property string|null $weight_rule 重量规则
 * @property int|null $status 状态 0：禁用  1：启用
 * @property string|null $memo 备注
 * @property \Illuminate\Support\Carbon $create_time 创建时间
 * @property string $create_username 创建人
 * @property \Illuminate\Support\Carbon|null $update_time 更新时间
 * @property string|null $update_username 更新人
 * @property string|null $program_code 版本号
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Freight\InternationalOrderConfig newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Freight\InternationalOrderConfig newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Freight\InternationalOrderConfig query()
 * @mixin \Eloquent
 */
class InternationalOrderConfig extends EloquentModel
{
    protected $table = 'tb_sys_international_order_config';

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'country_id',
        'extra_charge_ratio',
        'vat_ratio',
        'max_length',
        'max_length_rule',
        'second_length',
        'second_length_rule',
        'min_length',
        'min_length_rule',
        'girth',
        'girth_rule',
        'weight',
        'weight_rule',
        'status',
        'memo',
        'create_time',
        'create_username',
        'update_time',
        'update_username',
        'program_code',
    ];
}
