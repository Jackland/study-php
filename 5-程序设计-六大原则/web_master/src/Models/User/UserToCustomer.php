<?php

namespace App\Models\User;

use Framework\Model\EloquentModel;

/**
 * App\Models\User\UserToCustomer
 *
 * @property int $user_id tb_sys_user中的id
 * @property int $account_manager_id oc_customer中的customer_id,customer_group_id为14
 * @property int|null $country_id 国别
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\User\UserToCustomer newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\User\UserToCustomer newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\User\UserToCustomer query()
 * @mixin \Eloquent
 */
class UserToCustomer extends EloquentModel
{
    protected $table = 'oc_sys_user_to_customer';
    protected $primaryKey = '';

    protected $fillable = [
        'user_id',
        'account_manager_id',
    ];
}
