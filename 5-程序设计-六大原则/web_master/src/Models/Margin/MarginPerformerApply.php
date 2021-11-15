<?php

namespace App\Models\Margin;

use Framework\Model\EloquentModel;

/**
 * App\Models\Margin\MarginPerformerApply
 *
 * @property int $id 自增主键
 * @property int agreement_id 协议ID，即tb_sys_margin_agreement主键
 * @property string performer_buyer_id 共同履约人BuyerId
 * @property string|null reason 原因
 * @property int|null check_result 审核结果 check_result字典值维护在oc_setting表,code:margin_performer_apply;部分列举0: 初始状态，1:审批通过，2:审批拒绝
 * @property int|null seller_approval_status seller审核结果0: 初始状态，1:审批通过，2:审批拒绝
 * @property string|null seller_approval_time seller审核时间
 * @property string|null memo 备注
 * @property string|null create_user_name 创建者
 * @property string|null create_time 创建时间,即申请时间
 * @property string|null update_user_name 修改者
 * @property string|null update_time 修改时间，即审批时间
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Margin\MarginPerformerApply newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Margin\MarginPerformerApply newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Margin\MarginPerformerApply query()
 * @mixin \Eloquent
 * @property string|null $seller_check_reason seller审核原因
 * @property string|null $program_code 程序号
 */
class MarginPerformerApply extends EloquentModel
{
    // #27869 现货保证金共同履约人添加之后审核流程去掉 用来区分需求前的申请和之后的申请
    const PROGRAM_CODE_V2 = 'v2';

    protected $table = 'tb_sys_margin_performer_apply';
    protected $primaryKey = 'id';
}
