<?php

namespace App\Models\Marketing;

use App\Enums\Marketing\CouponAuditStatus;
use App\Enums\Marketing\CouponStatus;
use Carbon\Carbon;
use Framework\Model\Eloquent\Builder;
use Framework\Model\EloquentModel;


/**
 * App\Models\Marketing\Coupon
 *
 * @property int $id 自增主键
 * @property int $coupon_template_id oc_marketing_coupon_template.id
 * @property string $coupon_no 优惠券编号
 * @property int $order_id 绑定的订单号
 * @property int $customer_id customer_id
 * @property \Illuminate\Support\Carbon $effective_time 优惠券生效时间
 * @property \Illuminate\Support\Carbon $expiration_time 优惠券失效时间
 * @property float $denomination 优惠券面额
 * @property float $order_amount 使用条件,订单不低于的金额(不包含运费)
 * @property bool $status 状态,1未使用,2为已使用，3为无效
 * @property bool $audit_status 审核状态，0为待审核,1为已通过,2为已驳回
 * @property string $remark 备注
 * @property bool $is_deleted 是否删除
 * @property string $create_time 创建时间
 * @property string $update_time 更新时间
 * @property string $used_time 优惠券使用时间
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Marketing\Coupon available()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Marketing\Coupon newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Marketing\Coupon newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Marketing\Coupon query()
 * @mixin \Eloquent
 */
class Coupon extends EloquentModel
{
    public const CREATED_AT = 'create_time';
    public const UPDATED_AT = 'update_time';

    protected $table = 'oc_marketing_coupon';
    protected $dates = [
        'effective_time',
        'expiration_time'
    ];

    protected $fillable = [
        'coupon_template_id',
        'coupon_no',
        'order_id',
        'customer_id',
        'effective_time',
        'expiration_time',
        'denomination',
        'order_amount',
        'status',
        'audit_status',
        'remark',
        'is_deleted',
        'create_time',
        'update_time',
    ];

    /**
     * 可用的
     * @param Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeAvailable(Builder $query)
    {
        return $query->where('is_deleted', 0)
            ->where('audit_status', CouponAuditStatus::PASS)
            ->where('status', CouponStatus::UNUSED)
            ->where('effective_time', '<=', Carbon::now())
            ->where('expiration_time', '>', Carbon::now());
    }
}
