<?php

namespace App\Models\Link;

use App\Models\CustomerPartner\ProductGroup;
use Framework\Model\EloquentModel;

/**
 * App\Models\Link\CustomerPartnerProductGroupLink
 *
 * @property int $id
 * @property int $product_group_id
 * @property int $product_id
 * @property string $add_time
 * @property int $status 1->有效；0->被删除
 * @property int $seller_id
 * @property string $update_time 更新時間,如果被删除，则为删除时间
 * @property-read \App\Models\CustomerPartner\ProductGroup $productGroup
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Link\CustomerPartnerProductGroupLink newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Link\CustomerPartnerProductGroupLink newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Link\CustomerPartnerProductGroupLink query()
 * @mixin \Eloquent
 */
class CustomerPartnerProductGroupLink extends EloquentModel
{
    protected $table = 'oc_customerpartner_product_group_link';

    protected $fillable = [
        'product_group_id',
        'product_id',
        'add_time',
        'status',
        'seller_id',
        'update_time',
    ];

    public function productGroup()
    {
        return $this->hasOne(ProductGroup::class, 'id', 'product_group_id');
    }
}
