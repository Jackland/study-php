<?php

namespace App\Models\FeeOrder;

use App\Models\Safeguard\SafeguardConfig;
use Framework\Model\EloquentModel;

/**
 * App\Models\Safeguard\FeeOrderSafeguardDetail
 *
 * @property int $id
 * @property int $fee_order_id 主表id，oc_fee_order表id
 * @property int $safeguard_config_id oc_safeguard_config.id
 * @property int $safeguard_bill_id oc_safeguard_bill.id
 * @property int|null $sales_order_id 销售订单id
 * @property string $safeguard_fee 费用
 * @property string $order_base_amount 订单总金额（费用基数）
 * @property \Illuminate\Support\Carbon $create_time 创建时间
 * @property \Illuminate\Support\Carbon $update_time 更新时间
 * @property-read SafeguardConfig $safeguardConfig
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\FeeOrder\FeeOrderSafeguardDetail newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\FeeOrder\FeeOrderSafeguardDetail newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\FeeOrder\FeeOrderSafeguardDetail query()
 * @mixin \Eloquent
 */
class FeeOrderSafeguardDetail extends EloquentModel
{
    protected $table = 'oc_fee_order_safeguard_detail';

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'fee_order_id',
        'safeguard_config_id',
        'safeguard_bill_id',
        'sales_order_id',
        'safeguard_fee',
        'order_base_amount',
        'create_time',
        'update_time',
    ];

    public function safeguardConfig()
    {
        return $this->belongsTo(SafeguardConfig::class);
    }
}
