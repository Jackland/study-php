<?php

namespace App\Models\CustomerPartner;

use Framework\Model\EloquentModel;

/**
 * App\Models\CustomerPartner\ProductGroup
 *
 * @property int $id
 * @property string $name
 * @property string $description
 * @property string $add_time
 * @property string $update_time
 * @property int $status 1->为有效；0->被删除
 * @property int $seller_id
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\CustomerPartner\ProductGroup newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\CustomerPartner\ProductGroup newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\CustomerPartner\ProductGroup query()
 * @mixin \Eloquent
 */
class ProductGroup extends EloquentModel
{
    protected $table = 'oc_customerpartner_product_group';

    protected $fillable = [
        'name',
        'description',
        'add_time',
        'update_time',
        'status',
        'seller_id',
    ];
}
