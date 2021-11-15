<?php

namespace App\Models\Customer;

use Framework\Model\EloquentModel;

/**
 * App\Models\Customer\Country
 *
 * @property int $country_id
 * @property string $name
 * @property string $iso_code_2
 * @property string $iso_code_3
 * @property string $address_format
 * @property int $postcode_required
 * @property int $status
 * @property int|null $currency_id
 * @property int $show_flag
 * @property int|null $sort
 * @property string|null $chinese_name 中文名称
 * @property-read \App\Models\Customer\Currency|null $currency
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Customer\Country newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Customer\Country newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Customer\Country query()
 * @mixin \Eloquent
 */
class Country extends EloquentModel
{
    protected $table = 'oc_country';
    protected $primaryKey = 'country_id';

    protected $fillable = [
        'name',
        'iso_code_2',
        'iso_code_3',
        'address_format',
        'postcode_required',
        'status',
        'currency_id',
        'show_flag',
        'sort',
        'chinese_name',
    ];

    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }

    /**
     * @return array
     */
    public static function getCodeNameMap(): array
    {
        return Country::queryRead()->get()->pluck('name', 'iso_code_3')->toArray();
    }
}
