<?php

namespace App\Models\SellerAsset;

use Framework\Model\EloquentModel;

/**
 * App\Models\SellerAsset\SellerAssetAlarmLineSetting
 *
 * @property int $id 主键ID自增
 * @property int|null $country_id 国别ID
 * @property string|null $second_alarm_line_min 二级警报线-MIN
 * @property string|null $second_alarm_line_max 二级警报线-MAX
 * @property string|null $first_alarm_line_min 一级警报线-MIN
 * @property string|null $first_alarm_line_max 一级警报线-MAX
 * @property string|null $memo 备注
 * @property string|null $create_user_name 创建者
 * @property \Illuminate\Support\Carbon|null $create_time 创建时间
 * @property string|null $update_user_name 修改者
 * @property \Illuminate\Support\Carbon|null $update_time 修改时间
 * @property string|null $program_code 程序号
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SellerAsset\SellerAssetAlarmLineSetting newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SellerAsset\SellerAssetAlarmLineSetting newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SellerAsset\SellerAssetAlarmLineSetting query()
 * @mixin \Eloquent
 */
class SellerAssetAlarmLineSetting extends EloquentModel
{
    protected $table = 'oc_seller_asset_alarm_line_setting';

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'country_id',
        'second_alarm_line_min',
        'second_alarm_line_max',
        'first_alarm_line_min',
        'first_alarm_line_max',
        'memo',
        'create_user_name',
        'create_time',
        'update_user_name',
        'update_time',
        'program_code',
    ];
}
