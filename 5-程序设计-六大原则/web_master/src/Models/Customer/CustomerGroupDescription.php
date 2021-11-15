<?php

namespace App\Models\Customer;

use Framework\Model\EloquentModel;

/**
 * App\Models\Customer\CustomerGroupDescription
 *
 * @property int $customer_group_id
 * @property int $language_id
 * @property string $name
 * @property string $description
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Customer\CustomerGroupDescription newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Customer\CustomerGroupDescription newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Customer\CustomerGroupDescription query()
 * @mixin \Eloquent
 */
class CustomerGroupDescription extends EloquentModel
{
    protected $table = 'oc_customer_group_description';
    protected $primaryKey = '';

    protected $dates = [
        
    ];

    protected $fillable = [
        'name',
        'description',
    ];
}
