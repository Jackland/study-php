<?php

namespace App\Models\Safeguard;

use App\Models\SalesOrder\CustomerSalesOrder;
use Framework\Model\EloquentModel;

/**
 * App\Models\Safeguard\SafeguardClaimDetail
 *
 * @property int $id ID
 * @property int $claim_id oc_safeguard_claim.id
 * @property int $product_id 此字段值作信息展示用
 * @property string $item_code
 * @property int $sale_order_id 销售订单id
 * @property int $sale_order_line_id 销售订单明细id
 * @property int $qty 理赔数量
 * @property-read \App\Models\Safeguard\SafeguardClaimDetailTracking trackings
 * @property-read CustomerSalesOrder $salesOrder
 * @property \Illuminate\Support\Carbon $create_time 创建时间
 * @property \Illuminate\Support\Carbon $update_time 更新时间
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Safeguard\SafeguardClaimDetailTracking[] $trackings
 * @property-read int|null $trackings_count
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Safeguard\SafeguardClaimDetail newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Safeguard\SafeguardClaimDetail newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Safeguard\SafeguardClaimDetail query()
 * @mixin \Eloquent
 */
class SafeguardClaimDetail extends EloquentModel
{
    protected $table = 'oc_safeguard_claim_detail';

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'claim_id',
        'item_code',
        'sale_order_id',
        'sale_order_line_id',
        'qty',
        'create_time',
        'update_time',
    ];

    public function trackings()
    {
        return $this->hasMany(SafeguardClaimDetailTracking::class, 'claim_detail_id');
    }

    public function salesOrder()
    {
        return $this->belongsTo(CustomerSalesOrder::class, 'sale_order_id','id');
    }

}
