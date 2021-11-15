<?php

namespace App\Models\CustomerPartner;

use Framework\Model\EloquentModel;

/**
 * App\Models\CustomerPartner\BuyerGroupLink
 *
 * @property int $id
 * @property int $buyer_group_id
 * @property int $buyer_id
 * @property \Illuminate\Support\Carbon $add_time
 * @property int $status 1->有效；0->被删除
 * @property int $seller_id
 */
class BuyerGroupLink extends EloquentModel
{
    protected $table = 'oc_customerpartner_buyer_group_link';

    protected $dates = [
        'add_time',
    ];

    protected $fillable = [
        'buyer_group_id',
        'buyer_id',
        'add_time',
        'status',
        'seller_id',
    ];
}
