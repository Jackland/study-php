<?php

namespace App\Models\Marketing;

use App\Enums\Common\YesNoEnum;
use App\Enums\Marketing\CouponTemplateAuditStatus;
use App\Enums\Marketing\CouponTemplateStatus;
use Carbon\Carbon;
use Framework\Model\EloquentModel;


/**
 * 优惠券模板
 *
 * @property int $id 自增主键
 * @property string $name 优惠券名称
 * @property bool $type 优惠券类型,1领取型,2买够送,3直接发放定额,4直接发放非定额
 * @property int $country_id oc_country.country_id
 * @property string $grant_start_time 发放开始时间
 * @property string $grant_end_time 发放结束时间
 * @property \Illuminate\Support\Carbon|null $effective_time 优惠券生效时间
 * @property \Illuminate\Support\Carbon|null $expiration_time 优惠券失效时间
 * @property int $expiration_days 优惠券生效天数
 * @property float $denomination 优惠券面额
 * @property int $qty 总发行量
 * @property int|null $remain_qty 剩余数量(针对于领取型)
 * @property bool $buyer_scope 发放的Buyer,1为New buyer,2为Old buyer,3为所有
 * @property float $order_amount 订单不低于的金额(不包含运费)
 * @property bool $per_limit 每人限领数量
 * @property bool $status 状态,1为开启，2为停止
 * @property bool $audit_status 审核状态，0为待审核,1为已通过,2为已驳回
 * @property string $remark 备注
 * @property bool $is_deleted 是否删除
 * @property string $create_time 创建时间
 * @property string $update_time 更新时间
 * @property-read bool $is_available
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Marketing\CouponTemplate newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Marketing\CouponTemplate newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Marketing\CouponTemplate query()
 * @mixin \Eloquent
 */
class CouponTemplate extends EloquentModel
{
    const UPDATED_AT = 'update_time';

    protected $table = 'oc_marketing_coupon_template';
    protected $dates = [
        'effective_time',
        'expiration_time'
    ];

    protected $fillable = [
        'name',
        'type',
        'country_id',
        'grant_start_time',
        'grant_end_time',
        'effective_time',
        'expiration_time',
        'expiration_days',
        'denomination',
        'qty',
        'remain_qty',
        'buyer_scope',
        'order_amount',
        'per_limit',
        'status',
        'audit_status',
        'remark',
        'is_deleted',
        'create_time',
        'update_time',
    ];

    /**
     * 优惠券模板是否有效
     * @return bool
     */
    public function getIsAvailableAttribute(): bool
    {
        if ($this->is_deleted != YesNoEnum::NO) {
            return false;
        }

        if ($this->audit_status != CouponTemplateAuditStatus::PASS) {
            return false;
        }

        if ($this->status != CouponTemplateStatus::START) {
            return false;
        }

        if ($this->grant_start_time > Carbon::now() || $this->grant_end_time <= Carbon::now()) {
            return false;
        }

        return true;
    }
}
