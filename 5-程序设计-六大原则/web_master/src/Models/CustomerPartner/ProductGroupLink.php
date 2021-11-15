<?php

namespace App\Models\CustomerPartner;

use Framework\Model\EloquentModel;

/**
 * App\Models\CustomerPartner\CustomerPartnerProductGroupLink
 *
 * @property int $id
 * @property int $product_group_id
 * @property int $product_id
 * @property \Illuminate\Support\Carbon $add_time
 * @property int $status 1->有效；0->被删除
 * @property int $seller_id
 * @property \Illuminate\Support\Carbon $update_time 更新時間,如果被删除，则为删除时间
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\CustomerPartner\ProductGroupLink newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\CustomerPartner\ProductGroupLink newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\CustomerPartner\ProductGroupLink query()
 * @mixin \Eloquent
 */
class ProductGroupLink extends EloquentModel
{
    protected $table = 'oc_customerpartner_product_group_link';

    protected $dates = [
        'add_time',
        'update_time',
    ];

    protected $fillable = [
        'product_group_id',
        'product_id',
        'add_time',
        'status',
        'seller_id',
        'update_time',
    ];
}
