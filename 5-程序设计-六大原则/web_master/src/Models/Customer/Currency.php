<?php

namespace App\Models\Customer;

use Framework\Model\EloquentModel;

/**
 * App\Models\Customer\Currency
 *
 * @property int $currency_id
 * @property string $title
 * @property string $code
 * @property string $symbol_left
 * @property string $symbol_right
 * @property string $decimal_place
 * @property float $value
 * @property int $status
 * @property string $date_modified
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Customer\Currency newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Customer\Currency newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Customer\Currency query()
 * @mixin \Eloquent
 */
class Currency extends EloquentModel
{
    protected $table = 'oc_currency';
    protected $primaryKey = 'currency_id';

    protected $fillable = [
        'title',
        'code',
        'symbol_left',
        'symbol_right',
        'decimal_place',
        'value',
        'status',
        'date_modified',
    ];
}
