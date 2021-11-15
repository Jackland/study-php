<?php

namespace App\Models\Setting;

use Framework\Model\EloquentModel;

/**
 * App\Models\Setting\Setting
 *
 * @property int $setting_id
 * @property int $store_id
 * @property string $code
 * @property string $key
 * @property string $value
 * @property int $serialized
 * @property string|null $memo 备注说明
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Setting\Setting newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Setting\Setting newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Setting\Setting query()
 * @mixin \Eloquent
 */
class Setting extends EloquentModel
{
    protected $table = 'oc_setting';
    protected $primaryKey = 'setting_id';

    protected $fillable = [
        'store_id',
        'code',
        'key',
        'value',
        'serialized',
        'memo',
    ];
}
