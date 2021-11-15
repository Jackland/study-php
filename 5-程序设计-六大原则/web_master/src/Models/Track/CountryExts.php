<?php

namespace App\Models\Track;

use Framework\Model\EloquentModel;

/**
 * App\Models\Track\CountryExts
 *
 * @property int $id 自增主键
 * @property int $country_id 国家id
 * @property int|null $ship_day_min 物流时效最少用时，单位：天
 * @property int|null $ship_day_max 物流时效最多用时，单位：天
 * @property int|null $show_flag 是否在页面上显示标记：0-不显示，1-显示
 * @property int|null $type 产品类型：1-普通；2-LTL
 * @property \Illuminate\Support\Carbon|null $create_time 创建时间
 * @property \Illuminate\Support\Carbon|null $update_time 更新时间
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Track\CountryExts newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Track\CountryExts newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Track\CountryExts query()
 * @mixin \Eloquent
 */
class CountryExts extends EloquentModel
{
    protected $table = 'oc_country_exts';

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'country_id',
        'ship_day_min',
        'ship_day_max',
        'show_flag',
        'type',
        'create_time',
        'update_time',
    ];
}
