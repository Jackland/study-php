<?php

namespace App\Models\Marketing;

use Framework\Model\EloquentModel;

/**
 * App\Models\Marketing\CampaignRequest
 *
 * @property int $id
 * @property int $mc_id 活动主表
 * @property int $seller_id seller id
 * @property int $status 1->待审核;2->同意;3->拒绝;4->取消
 * @property string $banner_image banner图片地址(如果活动表类型为1，此字段才有值。)
 * @property string $banner_url banner 对应的页面地址(如果活动表类型为1，此字段才有值。)
 * @property string $banner_description banner 申请描述信息(如果活动表类型为1，此字段才有值。)
 * @property string $create_time 申请时间
 * @property string $update_time 申请时间
 * @property string|null $reject_reason 拒绝原因/部分通过原因
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Marketing\CampaignRequest newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Marketing\CampaignRequest newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Marketing\CampaignRequest query()
 * @mixin \Eloquent
 */
class CampaignRequest extends EloquentModel
{
    protected $table = 'oc_marketing_campaign_request';

    protected $fillable = [
        'mc_id',
        'seller_id',
        'status',
        'banner_image',
        'banner_url',
        'banner_description',
        'create_time',
        'update_time',
        'reject_reason',
    ];
}
