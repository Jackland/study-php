<?php

namespace App\Models\Bd;

use App\Models\Customer\Country;
use App\Models\Customer\Customer;
use Framework\Model\EloquentModel;

/**
 * App\Models\Bd\LeasingManager
 *
 * @property int $id
 * @property int $customer_id oc_customer.customer_id
 * @property \Illuminate\Support\Carbon $create_time
 * @property int $status 1->有效；0->无效
 * @property string $remark
 * @property int|null $country_id 招商DB对应的国别
 * @property string|null $region 招商BD对应的地区
 * @property int|null $user_id 招商经理后台管理系统的帐号ID
 * @property-read \App\Models\Customer\Customer $buyer
 * @property-read \App\Models\Customer\Country|null $country
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Bd\LeasingManager newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Bd\LeasingManager newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Bd\LeasingManager query()
 * @mixin \Eloquent
 */
class LeasingManager extends EloquentModel
{
    protected $table = 'oc_leasing_manager';

    protected $dates = [
        'create_time',
    ];

    protected $fillable = [
        'customer_id',
        'create_time',
        'status',
        'remark',
        'country_id',
        'region',
        'user_id',
    ];

    public function buyer()
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }

    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id', 'country_id');
    }

}
