<?php

namespace App\Models\Platform;

use Framework\Model\EloquentModel;

/**
 * App\Models\Platform\Platform
 *
 * @property int $platform_id
 * @property string $name
 * @property \Illuminate\Support\Carbon|null $date_add
 * @property \Illuminate\Support\Carbon|null $date_modified
 * @property int $sort_order 升序排列
 * @property int|null $is_deleted
 * @property int|null $inner_visible 内部可见标志 0:不可见 1:可见
 * @property int|null $outer_visible 外部可见标志 0:不可见 1:可见
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Platform\Platform newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Platform\Platform newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Platform\Platform query()
 * @mixin \Eloquent
 */
class Platform extends EloquentModel
{
    protected $table = 'oc_platform';
    protected $primaryKey = 'platform_id';

    protected $dates = [
        'date_add',
        'date_modified',
    ];

    protected $fillable = [
        'name',
        'date_add',
        'date_modified',
        'sort_order',
        'is_deleted',
        'inner_visible',
        'outer_visible',
    ];
}
