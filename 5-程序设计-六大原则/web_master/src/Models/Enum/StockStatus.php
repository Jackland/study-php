<?php

namespace App\Models\Enum;

use Framework\Model\EloquentModel;

/**
 * App\Models\Enum\StockStatus
 *
 * @property int $stock_status_id
 * @property int $language_id
 * @property string $name
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Enum\StockStatus newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Enum\StockStatus newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Enum\StockStatus query()
 * @mixin \Eloquent
 */
class StockStatus extends EloquentModel
{
    protected $table = 'oc_stock_status';
    protected $primaryKey = 'stock_status_id';

    protected $fillable = [
        'name',
    ];
}
