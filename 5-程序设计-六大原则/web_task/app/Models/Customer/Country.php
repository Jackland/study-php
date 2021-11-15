<?php

namespace App\Models\Customer;

use Illuminate\Database\Eloquent\Model;

/**
 * \App\Models\Customer\Country
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
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Customer\Country newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Customer\Country newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Customer\Country query()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Customer\Country whereAddressFormat($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Customer\Country whereChineseName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Customer\Country whereCountryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Customer\Country whereCurrencyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Customer\Country whereIsoCode2($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Customer\Country whereIsoCode3($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Customer\Country whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Customer\Country wherePostcodeRequired($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Customer\Country whereShowFlag($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Customer\Country whereSort($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Customer\Country whereStatus($value)
 * @mixin \Eloquent
 */
class Country extends Model
{
    protected $table = 'oc_country';
    protected $primaryKey = 'country_id';
}