<?php

namespace App\Models\Customer;

use Framework\Model\EloquentModel;

/**
 * App\Models\Customer\CustomerTelephoneVerifyHistory
 *
 * @property int $id
 * @property int $customer_id
 * @property int $telephone_country_code_id 手机号国家码, oc_telephone_country_code 表的 id
 * @property string $telephone 手机号
 * @property \Illuminate\Support\Carbon $telephone_verified_time 手机号验证时间
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Customer\CustomerTelephoneVerifyHistory newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Customer\CustomerTelephoneVerifyHistory newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Customer\CustomerTelephoneVerifyHistory query()
 * @mixin \Eloquent
 */
class CustomerTelephoneVerifyHistory extends EloquentModel
{
    protected $table = 'oc_customer_telephone_verify_history';

    protected $dates = [
        'telephone_verified_time',
    ];

    protected $fillable = [
        'customer_id',
        'telephone_country_code_id',
        'telephone',
        'telephone_verified_time',
    ];
}
