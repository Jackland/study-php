<?php

namespace App\Models\Product\Option;

use Framework\Model\EloquentModel;

/**
 * App\Models\Product\Option\Option
 *
 * @property int $option_id
 * @property string $type
 * @property int $sort_order
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\Option\Option newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\Option\Option newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\Option\Option query()
 * @mixin \Eloquent
 */
class Option extends EloquentModel
{
    const MIX_OPTION_ID = 13; // 旧的color属于，因存在其他属性的值已污染，固取名为mix
    const COLOR_OPTION_ID = 14; // 颜色
    const MATERIAL_OPTION_ID = 15; // 材质

    protected $table = 'oc_option';
    protected $primaryKey = 'option_id';

    protected $dates = [

    ];

    protected $fillable = [
        'type',
        'sort_order',
    ];
}
