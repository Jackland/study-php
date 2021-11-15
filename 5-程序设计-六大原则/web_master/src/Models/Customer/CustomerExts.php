<?php

namespace App\Models\Customer;

use App\Models\CustomerPartner\CustomerPartnerToProduct;
use Framework\Model\EloquentModel;

/**
 * App\Models\Customer\CustomerExts
 *
 * @property int $customer_id oc_customer表主键
 * @property int $auto_buy 是否需要自动购买:  1 是  0 否
 * @property int $import_order 是否可以导单: 1 是 0 否
 * @property int|null $second_passwd 是否需要二级密码: 1 是 0 否
 * @property int|null $ship_from_label 是否开启上门取件Label打印权限 0：否 1：是
 * @property int|null $agent_operation 0：非代运营，1：代运营，2：无此项业务
 * @property \Illuminate\Support\Carbon|null $create_time
 * @property string|null $create_user
 * @property \Illuminate\Support\Carbon|null $update_time
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Customer\CustomerExts newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Customer\CustomerExts newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Customer\CustomerExts query()
 * @mixin \Eloquent
 */
class CustomerExts extends EloquentModel
{
    protected $table = 'oc_customer_exts';
    protected $primaryKey = 'customer_id';

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'auto_buy',
        'import_order',
        'second_passwd',
        'ship_from_label',
        'agent_operation',
        'create_time',
        'create_user',
        'update_time',
    ];

    public function customerpartnerToProduct()
    {
        return $this->hasOne(CustomerPartnerToProduct::class, 'customer_id', 'customer_id');
    }
}
