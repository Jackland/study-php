<?php

namespace App\Models\Rebate;

use Framework\Model\EloquentModel;

/**
 * \App\Models\Rebate\RebateAgreementItem
 *
 * @property int $id 自增主键
 * @property int $agreement_id 主表主键ID
 * @property int $agreement_template_item_id 固化模板项ID
 * @property int $product_id 产品ID
 * @property float $template_price 模板价格
 * @property float $rebate_amount 返点金额
 * @property float $min_sell_price 最小售卖价格
 * @property string|null $memo 备注
 * @property bool|null $is_delete 是否删除，default：0-删除product时，修改为1
 * @property string|null $create_user_name 创建者
 * @property string $create_time 创建时间
 * @property string|null $update_user_name 修改者
 * @property string $update_time 修改时间
 * @property string|null $program_code 程序号
 * @property-read \App\Models\Rebate\RebateAgreement $rebateAgreement
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Rebate\RebateAgreementItem newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Rebate\RebateAgreementItem newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Rebate\RebateAgreementItem query()
 * @mixin \Eloquent
 */
class RebateAgreementItem extends EloquentModel
{
    protected $table = 'oc_rebate_agreement_item';

    public function rebateAgreement()
    {
        return $this->belongsTo(RebateAgreement::class, 'agreement_id');
    }
}
