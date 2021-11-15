<?php

namespace App\Models\Setting;

use Framework\Model\EloquentModel;

/**
 * App\Models\Setting\Parameter
 *
 * @property int $Id 自增主键
 * @property string $ParamKey 系统参数key值
 * @property string|null $ParamValue 系统参数value
 * @property string|null $Description 系统参数作用描述
 * @property string $DefaultValue 默认值
 * @property string|null $create_user_name 创建者
 * @property \Illuminate\Support\Carbon|null $create_time 创建时间
 * @property string|null $update_user_name 修改者
 * @property \Illuminate\Support\Carbon|null $update_time 修改时间
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Setting\Parameter newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Setting\Parameter newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Setting\Parameter query()
 * @mixin \Eloquent
 */
class Parameter extends EloquentModel
{
    protected $table = 'tb_sys_parameter';
    protected $primaryKey = 'Id';

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'ParamKey',
        'ParamValue',
        'Description',
        'DefaultValue',
        'create_user_name',
        'create_time',
        'update_user_name',
        'update_time',
    ];
}
