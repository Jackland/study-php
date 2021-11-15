<?php

namespace App\Models\Safeguard;

use Framework\Model\EloquentModel;

/**
 * App\Models\Safeguard\SafeguardClaimDetailTracking
 *
 * @property int $id ID
 * @property int $claim_id oc_safeguard_claim.id
 * @property int $claim_detail_id oc_safeguard_claim_detail.id
 * @property string $item_code
 * @property int $carrie_id 物流公司ID
 * @property string $carrier 物流公司
 * @property string $tracking_number tb_sys_customer_sales_order_tracking.tracking_number
 * @property \Illuminate\Support\Carbon $create_time 创建时间
 * @property \Illuminate\Support\Carbon $update_time 更新时间
 * @property int $carrier_id 物流公司ID
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Safeguard\SafeguardClaimDetailTracking newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Safeguard\SafeguardClaimDetailTracking newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Safeguard\SafeguardClaimDetailTracking query()
 * @mixin \Eloquent
 */
class SafeguardClaimDetailTracking extends EloquentModel
{
    protected $table = 'oc_safeguard_claim_detail_tracking';

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'claim_id',
        'claim_detail_id',
        'item_code',
        'carrie_id',
        'carrier',
        'tracking_number',
        'create_time',
        'update_time',
    ];
}
