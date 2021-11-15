<?php

namespace App\Models;

use Framework\Model\EloquentModel;

/**
 * App\Models\TelephoneCountryCode
 *
 * @property int $id
 * @property int $code 国家码
 * @property string $desc_cn 描述-中文
 * @property string $desc_en 描述-英文
 * @property \Illuminate\Support\Carbon $create_time 创建时间
 * @property \Illuminate\Support\Carbon $update_time 修改时间
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\TelephoneCountryCode newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\TelephoneCountryCode newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\TelephoneCountryCode query()
 * @mixin \Eloquent
 */
class TelephoneCountryCode extends EloquentModel
{
    protected $table = 'oc_telephone_country_code';

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'code',
        'desc_cn',
        'desc_en',
        'create_time',
        'update_time',
    ];
}
