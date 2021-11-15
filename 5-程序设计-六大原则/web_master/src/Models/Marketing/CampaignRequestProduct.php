<?php

namespace App\Models\Marketing;

use Framework\Model\EloquentModel;

/**
 * App\Models\Marketing\CampaignRequestProduct
 *
 * @property int $id
 * @property int $mc_id 活动主表
 * @property int $mc_request_id 活动申请表id oc_marketing_campaign_request.id
 * @property int $product_id oc_product.product_id
 * @property int $approval_status 审核状态 1->待审核;2->同意;3->拒绝;4->取消
 * @property int $status 1->有效;0->被删除
 * @property string|null $delete_time 被删除时间
 * @property string $create_time 申请时间
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Marketing\CampaignRequestProduct newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Marketing\CampaignRequestProduct newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Marketing\CampaignRequestProduct query()
 * @mixin \Eloquent
 */
class CampaignRequestProduct extends EloquentModel
{
    protected $table = 'oc_marketing_campaign_request_product';

    protected $fillable = [
        'mc_id',
        'mc_request_id',
        'product_id',
        'approval_status',
        'status',
        'delete_time',
        'create_time',
    ];
}
