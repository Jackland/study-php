<?php

namespace App\Models\Safeguard;

use Framework\Model\EloquentModel;

/**
 * App\Models\Safeguard\SafeguardConfigCountry
 *
 * @property int $id ID
 * @property int $safeguard_config_id oc_safeguard_config.id
 * @property int $country_id oc_country.country_id
 * @property int $status 0:失效,1:生效
 * @property \Illuminate\Support\Carbon $create_time 创建时间
 * @property \Illuminate\Support\Carbon $update_time 更新时间
 * @property-read SafeguardConfig $config
 * @property int $safeguard_config_rid oc_safeguard_config.rid
 * @property bool $is_executed 是否执行过,0:未执行过,1:执行过
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Safeguard\SafeguardConfigCountry newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Safeguard\SafeguardConfigCountry newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Safeguard\SafeguardConfigCountry query()
 * @mixin \Eloquent
 */
class SafeguardConfigCountry extends EloquentModel
{
    protected $table = 'oc_safeguard_config_country';

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'safeguard_config_id',
        'country_id',
        'status',
        'create_time',
        'update_time',
    ];

    public function config()
    {
        return $this->belongsTo(SafeguardConfig::class, 'safeguard_config_id');
    }

}
