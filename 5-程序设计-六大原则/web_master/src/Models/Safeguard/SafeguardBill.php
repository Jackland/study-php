<?php

namespace App\Models\Safeguard;

use Framework\Model\EloquentModel;
use App\Models\SalesOrder\CustomerSalesOrder;
use App\Enums\Safeguard\SafeguardBillStatus;

/**
 * App\Models\Safeguard\SafeguardBill
 *
 * @property int $id ID
 * @property int $buyer_id
 * @property int $country_id oc_country.country_id
 * @property string $safeguard_no 保单号
 * @property int $safeguard_config_id oc_safeguard_config.id
 * @property int $safeguard_config_rid oc_safeguard_config.rid
 * @property int $order_id 订单id，tb_sys_customer_sales_order.id
 * @property int $order_type 订单类型1-销售订单,
 * @property \Illuminate\Support\Carbon|null $effective_time 保障服务生效时间
 * @property \Illuminate\Support\Carbon|null $expiration_time 保障服务失效时间
 * @property int|null $status 1:保障中,2:已取消
 * @property \Illuminate\Support\Carbon|null $cancel_time 取消时间
 * @property \Illuminate\Support\Carbon $create_time 创建时间
 * @property \Illuminate\Support\Carbon $update_time 更新时间
 * @property-read CustomerSalesOrder $salesOrder
 * @property-read SafeguardConfig $safeguardConfig
 * @property-read SafeguardClaim $safeguardClaim
 * @property-read mixed $status_color
 * @property-read mixed $status_show
 * @property-read int|null $safeguard_claim_count
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Safeguard\SafeguardBill newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Safeguard\SafeguardBill newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Safeguard\SafeguardBill query()
 * @mixin \Eloquent
 */
class SafeguardBill extends EloquentModel
{
    protected $table = 'oc_safeguard_bill';

    protected $dates = [
        'effective_time',
        'expiration_time',
        'cancel_time',
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'buyer_id',
        'country_id',
        'safeguard_no',
        'safeguard_config_id',
        'safeguard_config_rid',
        'order_id',
        'order_type',
        'effective_time',
        'expiration_time',
        'status',
        'cancel_time',
        'create_time',
        'update_time',
    ];

    public function getStatusShowAttribute()
    {
        return SafeguardBillStatus::getDescription($this->attributes['status'] ?? '');
    }

    public function salesOrder()
    {
        return $this->belongsTo(CustomerSalesOrder::class, 'order_id');
    }

    public function safeguardConfig()
    {
        return $this->belongsTo(SafeguardConfig::class, 'safeguard_config_id');
    }


    public function safeguardClaim()
    {
        return $this->hasMany(SafeguardClaim::class);
    }

    public function getStatusColorAttribute()
    {
        return SafeguardBillStatus::getColorDescription($this->attributes['status'] ?? '');
    }
}
