<?php

namespace App\Models\Safeguard;

use Framework\Model\EloquentModel;
use App\Enums\Safeguard\SafeguardClaimStatus;

/**
 * App\Models\Safeguard\SafeguardClaim
 *
 * @property int $id ID
 * @property string $claim_no 理赔单号
 * @property int $safeguard_bill_id oc_safeguard_bill.id
 * @property int $buyer_id buyer_id
 * @property string $claim_amount 理赔金额
 * @property int|null $status 10:理赔中,11:打回完善资料,20:理赔成功,30:理赔失败,
 * @property string|null $payment_method 赔付到账方式
 * @property \Illuminate\Support\Carbon|null $paid_time 赔付到账时间
 * @property int|null $is_viewed buyer是否已查看 0:否 1:是
 * @property int|null $refund_type 1全部退款,2:部分退款
 * @property int $audit_id oc_safeguard_claim_audit.id
 * @property int $expired_type 超时标记：0-正常，1-即将超时，2-已经超时
 * @property-read SafeguardBill $safeguardBill
 * @property-read SafeguardClaimDetail $claimDetails
 * @property \Illuminate\Support\Carbon $create_time 创建时间
 * @property \Illuminate\Support\Carbon $update_time 更新时间
 * @property string $status_show
 * @property string $status_color
 * @property-read int|null $claim_details_count
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Safeguard\SafeguardClaim newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Safeguard\SafeguardClaim newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Safeguard\SafeguardClaim query()
 * @mixin \Eloquent
 */
class SafeguardClaim extends EloquentModel
{
    protected $table = 'oc_safeguard_claim';

    protected $appends = ['status_show','status_color'];

    protected $dates = [
        'paid_time',
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'claim_no',
        'safeguard_bill_id',
        'buyer_id',
        'claim_amount',
        'status',
        'payment_method',
        'paid_time',
        'is_viewed',
        'refund_type',
        'audit_id',
        'create_time',
        'update_time',
    ];

    public function safeguardBill()
    {
        return $this->belongsTo(SafeguardBill::class, 'safeguard_bill_id');
    }

    public function claimDetails()
    {
        return $this->hasMany(SafeguardClaimDetail::class, 'claim_id');
    }

    public function getStatusShowAttribute()
    {
        return SafeguardClaimStatus::getDescription($this->attributes['status'] ?? '');
    }

    public function getStatusColorAttribute()
    {
        return SafeguardClaimStatus::getColorDescription($this->attributes['status'] ?? '');
    }

}
