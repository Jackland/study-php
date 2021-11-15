<?php

namespace App\Models\Product\Channel;

use Framework\Model\EloquentModel;

/**
 * App\Models\Product\Channel\ChannelParamConfig
 *
 * @property int $id 主键id
 * @property string $name 频道名称
 * @property int $status 是否有效 0：无效 1：有效
 * @property \Illuminate\Support\Carbon $create_time 创建时间
 * @property string $create_name 创建人
 * @property \Illuminate\Support\Carbon|null $update_time 修改时间
 * @property string|null $update_name 修改名称
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Product\Channel\ChannelParamConfigValue[] $channelParamConfigValue
 * @property-read int|null $channel_param_config_value_count
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\Channel\ChannelParamConfig newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\Channel\ChannelParamConfig newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\Channel\ChannelParamConfig query()
 * @mixin \Eloquent
 * @property string $show_name 展示的名称
 */
class ChannelParamConfig extends EloquentModel
{
    protected $table = 'tb_channel_param_config';

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'name',
        'status',
        'create_time',
        'create_name',
        'update_time',
        'update_name',
    ];

    public function channelParamConfigValue(){
        return $this->hasMany(ChannelParamConfigValue::class, 'header_id');
    }
}
