<?php

namespace App\Models\Onsite;

use Framework\Model\EloquentModel;

/**
 * App\Models\Onsite\OnsiteFreightDetail
 *
 * @property int $id
 * @property int $version_id onsite_freight_version表主键
 * @property int $type 1 快递报价 2 卡车报价
 * @property string $key
 * @property string|array $value
 * @property string|null $description 字段描述
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Onsite\OnsiteFreightDetail newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Onsite\OnsiteFreightDetail newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Onsite\OnsiteFreightDetail query()
 * @mixin \Eloquent
 */
class OnsiteFreightDetail extends EloquentModel
{
    protected $table = 'onsite_freight_detail';

    protected $dates = [

    ];

    protected $fillable = [
        'version_id',
        'type',
        'key',
        'value',
        'description',
    ];

}
