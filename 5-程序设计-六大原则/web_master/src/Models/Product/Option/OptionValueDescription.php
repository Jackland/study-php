<?php

namespace App\Models\Product\Option;

use Framework\Model\EloquentModel;

/**
 * App\Models\Product\Option\OptionValueDescription
 *
 * @property int $option_value_id
 * @property int $language_id
 * @property int $option_id
 * @property string $name
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\Option\OptionValueDescription newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\Option\OptionValueDescription newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\Option\OptionValueDescription query()
 * @mixin \Eloquent
 */
class OptionValueDescription extends EloquentModel
{
    protected $table = 'oc_option_value_description';
    protected $primaryKey = '';

    protected $dates = [

    ];

    protected $fillable = [
        'option_id',
        'name',
    ];
}
