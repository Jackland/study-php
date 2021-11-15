<?php


namespace App\Models\MarketingCoupon;


use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    public const CREATED_AT = 'create_time';
    public const UPDATED_AT = 'update_time';

    protected $table = 'oc_marketing_coupon';

    const STATUS_UNUSED = 1; // 优惠券未使用
    const STATUS_USED = 2; // 优惠券已使用
    const STATUS_INVALID = 3; // 优惠券已经无效

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
     * 设置优惠券为未使用
     * @param $orderId
     */
    public static function cancelCouponUsed($orderId)
    {
        // 1. 设置优惠券为未已使用
        Coupon::where('order_id', $orderId)
            ->where('status', static::STATUS_USED)
            ->update(['status' => static::STATUS_UNUSED, 'order_id' => 0, 'used_time' => 0]);
    }


}