<?php

namespace App\Models\Product\Channel;

use Framework\Model\EloquentModel;

/**
 * App\Models\Product\Channel\HomePageConfig
 *
 * @property int $id 主键id
 * @property int $type_id 1：feature store  2：new store
 * @property int $country_id 国别
 * @property int $status 是否有效 0：无效 1：有效
 * @property string $create_name 创建人
 * @property \Illuminate\Support\Carbon $create_time 创建时间
 * @property \Illuminate\Support\Carbon $update_time 更新时间
 * @property string|null $update_name 修改名称
 * @property string|null $content json内容
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\Channel\HomePageConfig newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\Channel\HomePageConfig newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\Channel\HomePageConfig query()
 * @mixin \Eloquent
 */
class HomePageConfig extends EloquentModel
{
    protected $table = 'tb_sys_home_page_config';

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'type_id',
        'country_id',
        'status',
        'create_name',
        'create_time',
        'update_time',
        'update_name',
        'content',
    ];
}
