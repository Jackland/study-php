<?php

namespace App\Models\Product\Channel;

use Framework\Model\EloquentModel;

/**
 * App\Models\Product\Channel\ChannelParamConfigValue
 *
 * @property int $id 主键id
 * @property int $header_id 频道参数设置名称主键id
 * @property string $param_name 参数名称
 * @property string $param_value 参数权重
 * @property int $status 是否有效 0：无效 1：有效
 * @property \Illuminate\Support\Carbon $create_time 创建时间
 * @property string $create_name 创建人
 * @property \Illuminate\Support\Carbon|null $update_time 修改时间
 * @property string|null $update_name 修改人
 * @property-read \App\Models\Product\Channel\ChannelParamConfig $channelParamConfig
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\Channel\ChannelParamConfigValue newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\Channel\ChannelParamConfigValue newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\Channel\ChannelParamConfigValue query()
 * @mixin \Eloquent
 */
class ChannelParamConfigValue extends EloquentModel
{
    protected $table = 'tb_channel_param_config_value';

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'header_id',
        'param_name',
        'param_value',
        'status',
        'create_time',
        'create_name',
        'update_time',
        'update_name',
    ];

    public function channelParamConfig(){
        return $this->belongsTo(ChannelParamConfig::class, 'header_id');
    }
}
