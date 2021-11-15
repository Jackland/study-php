<?php

namespace App\Models\Customer;

use Framework\Model\EloquentModel;

/**
 * App\Models\Customer\CustomerTip
 *
 * @property int $id 自增主键
 * @property int $customer_id
 * @property string $type_key 类型
 * @property \Illuminate\Support\Carbon $create_time 创建时间
 * @property \Illuminate\Support\Carbon $update_time 更新时间
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Customer\CustomerTip newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Customer\CustomerTip newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Customer\CustomerTip query()
 * @mixin \Eloquent
 */
class CustomerTip extends EloquentModel
{
    protected $table = 'oc_customer_tip';

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'customer_id',
        'type_key',
        'create_time',
        'update_time',
    ];
}
