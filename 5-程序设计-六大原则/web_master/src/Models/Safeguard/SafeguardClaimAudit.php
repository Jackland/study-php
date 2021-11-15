<?php

namespace App\Models\Safeguard;

use App\Enums\Common\YesNoEnum;
use Framework\Model\EloquentModel;
use App\Models\Attach\FileUploadDetail;

/**
 * App\Models\Safeguard\SafeguardClaimAudit
 *
 * @property int $id ID
 * @property int $picked_user_id 审核领取人tb_sys_user.id
 * @property int $user_id tb_sys_user.id
 * @property int $next_user_id 下一级审核人tb_sys_user.id
 * @property int $buyer_id oc_customer.id
 * @property int $claim_id oc_safeguard_claim.id
 * @property int $role_id tb_sys_role.id
 * @property int $reason_id 理赔原因ID
 * @property string $reason_name 理赔原因
 * @property string $sales_platform 第三方销售平台
 * @property string $content 描述
 * @property string $inner_content 内部备注
 * @property string $claim_amount 理赔金额
 * @property int|null $status 0:buyer申请理赔,10:审批中,11:打回完善资料,20:资料已完善,30:审批通过,40:驳回,
 * @property int $attach_menu_id 附件主ID
 * @property string $remark 备注
 * @property \Illuminate\Support\Carbon $create_time 创建时间
 * @property \Illuminate\Support\Carbon $update_time 更新时间
 * @property-read FileUploadDetail $attachs
 * @property bool|null $refund_type 1全部退款,2:部分退款
 * @property string $reason_name_zh 理赔原因中文
 * @property-read int|null $attachs_count
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Safeguard\SafeguardClaimAudit newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Safeguard\SafeguardClaimAudit newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Safeguard\SafeguardClaimAudit query()
 * @mixin \Eloquent
 */
class SafeguardClaimAudit extends EloquentModel
{
    protected $table = 'oc_safeguard_claim_audit';

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'picked_user_id',
        'user_id',
        'next_user_id',
        'buyer_id',
        'claim_id',
        'role_id',
        'reason_id',
        'reason_name',
        'sales_platform',
        'content',
        'inner_content',
        'claim_amount',
        'status',
        'attach_menu_id',
        'remark',
        'create_time',
        'update_time',
    ];

    public function attachs()
    {
        return $this->hasMany(FileUploadDetail::class, 'menu_id', 'attach_menu_id')
            ->where('delete_flag', YesNoEnum::NO)
            ->where('file_status', YesNoEnum::NO)
            ->whereNull('reserved_field');
    }

}
