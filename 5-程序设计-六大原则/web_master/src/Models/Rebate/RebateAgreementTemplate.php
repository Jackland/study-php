<?php

namespace App\Models\Rebate;

use App\Models\Customer\Customer;
use Framework\Model\EloquentModel;

/**
 * App\Models\Rebate\RebateAgreementTemplate
 *
 * @property int $id 自增主键
 * @property string $rebate_template_id 模板ID (日期+6位递增序列 例如:20190911000001)
 * @property int $seller_id SellerId
 * @property int $day 合同限定天数
 * @property int $qty 合同限定数量
 * @property int $rebate_type 返点类型 (0:按比例、1:固定金额)
 * @property string $rebate_value 返点数值
 * @property int $limit_num -1:no limit\r\n>=0：有指定数量的限制
 * @property string $search_product 记录页面搜索用的product sku或mpn ，copy和edit时使用
 * @property string|null $items 产品项 (存储格式【SKU1:MPN1,SKU2:MPN2 ...】)
 * @property int|null $item_num 产品项总数
 * @property string|null $item_price 产品项价格区间 (单独数字，或已"_"分割的区间，如: 20 或 20_50)
 * @property string|null $item_rebates 产品项返点区间 (\r\n数字【例：50】：选择固定金额且保存时没做变动；\r\n百分比【例：30%】：选择比例保存时没做变动；\r\n区间【例：30_60】：用户修改不同产品不同价格\r\n)
 * @property int $is_deleted 软删除
 * @property string|null $memo 备注
 * @property string|null $create_user_name 创建者
 * @property \Illuminate\Support\Carbon $create_time 创建时间
 * @property string|null $update_user_name 修改者
 * @property \Illuminate\Support\Carbon $update_time 修改时间
 * @property string|null $program_code 程序号
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Rebate\RebateAgreementTemplateItem[] $rebateAgreementTemplateItems
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Rebate\RebateAgreementTemplateItem[] $rebateAgreementTemplateItemsAvailable
 * @property-read int|null $rebate_agreement_template_items_count
 * @property-read \App\Models\Customer\Customer $seller
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Rebate\RebateAgreementTemplate newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Rebate\RebateAgreementTemplate newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Rebate\RebateAgreementTemplate query()
 * @mixin \Eloquent
 */
class RebateAgreementTemplate extends EloquentModel
{
    protected $table = 'oc_rebate_template';

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'rebate_template_id',
        'seller_id',
        'day',
        'qty',
        'rebate_type',
        'rebate_value',
        'limit_num',
        'search_product',
        'items',
        'item_num',
        'item_price',
        'item_rebates',
        'is_deleted',
        'memo',
        'create_user_name',
        'create_time',
        'update_user_name',
        'update_time',
        'program_code',
    ];

    public function seller()
    {
        return $this->belongsTo(Customer::class, 'seller_id');
    }

    public function rebateAgreementTemplateItems()
    {
        return $this->hasMany(RebateAgreementTemplateItem::class, 'template_id');
    }

    public function rebateAgreementTemplateItemsAvailable()
    {
        return $this->belongsTo(RebateAgreementTemplateItem::class, 'template_id')
            ->where('is_deleted', 0);
    }
}
