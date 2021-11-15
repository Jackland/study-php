<?php

namespace App\Models\Rebate;

use Framework\Model\EloquentModel;

/**
 * \App\Models\Rebate\RebateAgreementRequest
 *
 * @property int $id 自增主键
 * @property int $agreement_id 协议ID
 * @property int $buyer_id BuyerId
 * @property string|null $comments Buyer留言
 * @property string|null $reback Seller回复
 * @property bool $process_status 处理状态 (状态类型维护字典表中)
 * @property string|null $memo 备注
 * @property string|null $create_user_name 创建者
 * @property string|null $create_time 创建时间
 * @property string|null $update_user_name 修改者
 * @property string|null $update_time 修改时间
 * @property string|null $program_code 程序号
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Rebate\RebateAgreementRequest newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Rebate\RebateAgreementRequest newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Rebate\RebateAgreementRequest query()
 * @mixin \Eloquent
 */
class RebateAgreementRequest extends EloquentModel
{
    protected $table = 'oc_rebate_agreement_request';
}
