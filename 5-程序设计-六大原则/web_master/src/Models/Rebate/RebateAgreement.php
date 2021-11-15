<?php

namespace App\Models\Rebate;

use App\Models\Customer\Customer;
use Framework\Model\EloquentModel;

/**
 * \App\Models\Rebate\RebateAgreement
 *
 * @property int $id 自增主键
 * @property string $agreement_code 返点协议合同编号 (日期+6位自增序列号)
 * @property int $agreement_template_id 固化模板ID
 * @property int $buyer_id BuyerId
 * @property int $seller_id SellerId
 * @property int|null $day 合同限定销售天数
 * @property int|null $qty 合同限定最低销售数量
 * @property string|null $effect_time 合同生效时间
 * @property string|null $expire_time 合同过期时间
 * @property int $clauses_id 关联条款ID (oc_information.information_id)
 * @property bool $status 合同状态 (状态信息维护字典表中) Agreement status
 * @property string|null $remark 用户备注
 * @property string|null $memo 备注
 * @property string|null $create_user_name 创建者
 * @property string $create_time 创建时间
 * @property string|null $update_user_name 修改者
 * @property string $update_time 修改时间
 * @property string|null $program_code 程序号
 * @property bool|null $rebate_result 只有在status = 3 才有后续的状态
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Rebate\RebateAgreementProduct[] $agreementProducts
 * @property-read int|null $agreement_products_count
 * @property-read \App\Models\Customer\Customer $buyer
 * @property-read \App\Models\Customer\Customer $seller
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Rebate\RebateAgreement newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Rebate\RebateAgreement newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Rebate\RebateAgreement query()
 * @mixin \Eloquent
 */
class RebateAgreement extends EloquentModel
{
    protected $table = 'oc_rebate_agreement';

    public function agreementProducts()
    {
        return $this->hasMany(RebateAgreementProduct::class, 'agreement_id');
    }

    public function buyer()
    {
        return $this->belongsTo(Customer::class, 'buyer_id');
    }

    public function seller()
    {
        return $this->belongsTo(Customer::class, 'seller_id');
    }
}
