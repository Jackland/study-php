<?php

namespace App\Models\Marketing;

use Framework\Model\EloquentModel;

/**
 * App\Models\Marketing\CampaignOrder
 *
 * @property int $id 主键ID
 * @property int $order_id 订单号
 * @property int $mc_id 活动主表oc_marketing_campaign.id
 * @property string $minus_amount 满减金额
 * @property int $coupon_id oc_marketing_customer_coupon.id,赠送优惠券ID
 * @property string $remark 备注
 * @property string $create_time 创建时间
 * @property string $update_time 更新时间
 * @property-read Coupon $coupon
 * @property int $coupon_template_id oc_marketing_coupon_template.id,关联的优惠券模板
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Marketing\CampaignOrder newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Marketing\CampaignOrder newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Marketing\CampaignOrder query()
 * @mixin \Eloquent
 */
class CampaignOrder extends EloquentModel
{
    protected $table = 'oc_marketing_campaign_order';

    protected $fillable = [
        'order_id',
        'mc_id',
        'minus_amount',
        'coupon_template_id',
        'coupon_id',
        'remark',
        'create_time',
        'update_time',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }
}
