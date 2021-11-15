<?php

namespace App\Models\Product\Option;

use Framework\Model\EloquentModel;

/**
 * App\Models\Product\Option\OptionDescription
 *
 * @property int $option_id
 * @property int $language_id
 * @property string $name
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\Option\OptionDescription newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\Option\OptionDescription newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\Option\OptionDescription query()
 * @mixin \Eloquent
 */
class OptionDescription extends EloquentModel
{
    protected $table = 'oc_option_description';
    protected $primaryKey = '';

    protected $dates = [

    ];

    protected $fillable = [
        'name',
    ];
}
