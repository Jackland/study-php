<?php

namespace App\Models\Marketing;

use Framework\Model\EloquentModel;

/**
 * 优惠券操作日志
 *
 * @property int $id 自增主键
 * @property int $coupon_id 优惠券ID，oc_marketing_customer_coupon.id
 * @property int $operator_id 操作人ID
 * @property bool $type 1为新建,2编辑,3通过,4驳回,5删除
 * @property string $content 日志内容
 * @property string $create_time 创建时间
 * @property string $update_time 更新时间
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Marketing\CouponLog newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Marketing\CouponLog newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Marketing\CouponLog query()
 * @mixin \Eloquent
 */
class CouponLog extends EloquentModel
{
    protected $table = 'oc_marketing_coupon_log';

    protected $fillable = [
        'coupon_id',
        'operator_id',
        'type',
        'content',
        'create_time',
        'update_time',
    ];
}
