<?php

namespace App\Models\DelicacyManagement;

use App\Models\CustomerPartner\ProductGroupLink;
use Framework\Model\EloquentModel;

/**
 * App\Models\DelicacyManagement\DelicacyManagementGroup
 *
 * @property int $id
 * @property int $seller_id
 * @property int $buyer_group_id
 * @property int $product_group_id
 * @property \Illuminate\Support\Carbon $add_time 创建时间
 * @property int $status
 * @property \Illuminate\Support\Carbon $update_time 更新时间，如果被删除则为删除时间
 * @property-read \App\Models\DelicacyManagement\CustomerpartnerBuyerGroupLink $BuyerGroupLink
 * @property-read \App\Models\CustomerPartner\ProductGroupLink $ProductGroupLink
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\DelicacyManagement\DelicacyManagementGroup newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\DelicacyManagement\DelicacyManagementGroup newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\DelicacyManagement\DelicacyManagementGroup query()
 * @mixin \Eloquent
 */
class DelicacyManagementGroup extends EloquentModel
{
    protected $table = 'oc_delicacy_management_group';

    protected $dates = [
        'add_time',
        'update_time',
    ];

    protected $fillable = [
        'seller_id',
        'buyer_group_id',
        'product_group_id',
        'add_time',
        'status',
        'update_time',
    ];

    public function BuyerGroupLink()
    {
        return $this->belongsTo(CustomerpartnerBuyerGroupLink::class, 'buyer_group_id');
    }

    public function ProductGroupLink()
    {
        return $this->belongsTo(ProductGroupLink::class, 'product_group_id');
    }
}
