<?php

namespace App\Models\Rebate;

use Framework\Model\EloquentModel;

/**
 * App\Models\Rebate\RebateAgreementTemplateItem
 *
 * @property int $id 自增主键
 * @property int $template_id 返点模板主表ID
 * @property int $product_id 产品ID
 * @property string|null $price 返点货值价格
 * @property string|null $rebate_amount 返点金额
 * @property string|null $min_sell_price 最小售卖价格
 * @property string|null $memo 备注
 * @property int $is_deleted 是否删除
 * @property string|null $create_user_name 创建者
 * @property \Illuminate\Support\Carbon $create_time 创建时间
 * @property string|null $update_user_name 修改者
 * @property \Illuminate\Support\Carbon $update_time 修改时间
 * @property string|null $program_code 程序号
 * @property string $rest_price 返点货值价格减去返点金额用于排序优化
 * @property-read \App\Models\Rebate\RebateAgreementTemplate $rebateAgreementTemplate
 * @property-read \App\Models\Rebate\RebateAgreementTemplate $rebateAgreementTemplateAvailable
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Rebate\RebateAgreementTemplateItem newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Rebate\RebateAgreementTemplateItem newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Rebate\RebateAgreementTemplateItem query()
 * @mixin \Eloquent
 */
class RebateAgreementTemplateItem extends EloquentModel
{
    protected $table = 'oc_rebate_template_item';

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'template_id',
        'product_id',
        'price',
        'rebate_amount',
        'min_sell_price',
        'memo',
        'is_deleted',
        'create_user_name',
        'create_time',
        'update_user_name',
        'update_time',
        'program_code',
        'rest_price',
    ];

    public function rebateAgreementTemplate()
    {
        return $this->belongsTo(RebateAgreementTemplate::class, 'template_id');
    }

    public function rebateAgreementTemplateAvailable()
    {
        return $this->belongsTo(RebateAgreementTemplate::class, 'template_id')
            ->where('is_deleted', 0);
    }
}
