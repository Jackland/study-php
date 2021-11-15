<?php

namespace App\Models\Product\Option;

use App\Enums\Common\YesNoEnum;
use Framework\Model\Eloquent\Builder;
use Framework\Model\EloquentModel;

/**
 * App\Models\Product\Option\OptionValue
 *
 * @property int $option_value_id
 * @property int $option_id
 * @property string $image
 * @property int $sort_order
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\Option\OptionValue newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\Option\OptionValue newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\Option\OptionValue query()
 * @mixin \Eloquent
 * @property bool $status 1正常 0停用
 * @property bool $is_deleted 0未删 1已删
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\Option\OptionValue valid()
 */
class OptionValue extends EloquentModel
{
    protected $table = 'oc_option_value';
    protected $primaryKey = 'option_value_id';

    protected $dates = [

    ];

    protected $fillable = [
        'option_id',
        'image',
        'sort_order',
    ];

    /**
     * 有效的
     * @param Builder $builder
     * @return Builder
     */
    public function scopeValid(Builder $builder) :Builder
    {
        return $builder->where('status', YesNoEnum::YES)->where('is_deleted', YesNoEnum::NO);
    }
}
