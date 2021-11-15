<?php

namespace App\Models\Customer;

use App\Models\CustomerPartner\CustomerPartnerToCustomer;
use Framework\Model\EloquentModel;

/**
 * App\Models\CustomerComplaintBox
 *
 * @property int $id ID
 * @property int $complainant_id 投诉人的customer_id
 * @property int $respondent_id 被投诉人的customer_id
 * @property int $type 类型：1针对站内信 2针对用户
 * @property string|null $reason 理由
 * @property int $msg_id 消息oc_msg:id
 * @property int $status 状态：1未处理 2已处理
 * @property string|null $remark 备注
 * @property int $is_deleted
 * @property \Illuminate\Support\Carbon $create_time 创建时间
 * @property \Illuminate\Support\Carbon $update_time 更新时间
 * @property string|null $operator 操作者名称
 */
class CustomerComplaintBox extends EloquentModel
{
    protected $table = 'oc_customer_complaint_box';

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'complainant_id',
        'respondent_id',
        'type',
        'reason',
        'msg_id',
        'status',
        'remark',
        'is_deleted',
        'create_time',
        'update_time',
        'operator',
    ];

}
