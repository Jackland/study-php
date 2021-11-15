<?php

namespace App\Models\Marketing;

use Framework\Model\EloquentModel;


/**
 * App\Models\Marketing\CampaignCondition
 *
 * @property int $id 主键ID
 * @property int $mc_id 活动主表id
 * @property float $minus_amount 满减金额
 * @property float $order_amount 使用条件,订单不低于的金额(不包含运费)
 * @property int $coupon_template_id oc_marketing_coupon_template.id,关联的优惠券模板 -1表示其他
 * @property string $remark 备注
 * @property string $create_time 创建时间
 * @property string $update_time 更新时间
 * @property-read \App\Models\Marketing\CouponTemplate $couponTemplate
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Marketing\CampaignCondition newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Marketing\CampaignCondition newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Marketing\CampaignCondition query()
 * @mixin \Eloquent
 */
class CampaignCondition extends EloquentModel
{
    protected $table = 'oc_marketing_campaign_condition';

    protected $fillable = [
        'mc_id',
        'minus_amount',
        'order_amount',
        'coupon_template_id',
        'remark',
        'create_time',
        'update_time',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function couponTemplate()
    {
        return $this->belongsTo(CouponTemplate::class);
    }
}
