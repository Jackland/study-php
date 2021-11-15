<?php

namespace App\Models\Setting;

use Framework\Model\EloquentModel;

/**
 * App\Models\Setting\PriceSpecificSplit
 *
 * @property int $id primary key
 * @property int $country_id 适用国家id
 * @property string $factor 价格变化系数
 * @property string|null $description 记录适用场景描述
 * @property string|null $memo 备注
 * @property string|null $create_username 创建人
 * @property \Illuminate\Support\Carbon|null $create_date 创建时间
 * @property string|null $update_username 更新人
 * @property string|null $update_date 更新时间
 * @property string|null $programe_code 版本号
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Setting\PriceSpecificSplit newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Setting\PriceSpecificSplit newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Setting\PriceSpecificSplit query()
 * @mixin \Eloquent
 */
class PriceSpecificSplit extends EloquentModel
{
    protected $table = 'oc_price_specific_split';

    protected $dates = [
        'create_date',
    ];

    protected $fillable = [
        'country_id',
        'factor',
        'description',
        'memo',
        'create_username',
        'create_date',
        'update_username',
        'update_date',
        'programe_code',
    ];
}
