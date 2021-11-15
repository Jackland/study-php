<?php

namespace App\Models\CustomerPartner;

use Framework\Model\EloquentModel;

/**
 * App\Models\CustomerPartner\BuyerGroup
 *
 * @property int $id
 * @property string $name
 * @property string $description
 * @property \Illuminate\Support\Carbon $add_time
 * @property \Illuminate\Support\Carbon $update_time
 * @property int $status 1->为有效；0->被删除
 * @property int $is_default 1->默认
 * @property int $seller_id
 */
class BuyerGroup extends EloquentModel
{
    protected $table = 'oc_customerpartner_buyer_group';

    protected $dates = [
        'add_time',
        'update_time',
    ];

    protected $fillable = [
        'name',
        'description',
        'add_time',
        'update_time',
        'status',
        'is_default',
        'seller_id',
    ];
}
