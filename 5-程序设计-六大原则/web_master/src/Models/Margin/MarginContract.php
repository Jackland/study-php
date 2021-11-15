<?php

namespace App\Models\Margin;

use App\Enums\Common\YesNoEnum;
use Framework\Model\EloquentModel;

/**
 * App\Models\Margin\MarginContract
 *
 * @property int $id 自增主键
 * @property string $contract_no 合约编号
 * @property int $customer_id SellerId
 * @property int $product_id 产品ID
 * @property string $payment_ratio 支付保证金比例 20.00% 填写 20.00
 * @property int $bond_template_id 对应tb_bond_template表的ID
 * @property int $day 售卖天数
 * @property int $is_bid 是否可以bid 1可以 0不可以
 * @property int $status 合约状态，１可中，２禁用
 * @property int $is_history_contract 是否历史数据生成的合约
 * @property int $is_deleted 是否删除
 * @property \Illuminate\Support\Carbon $create_time 创建时间
 * @property \Illuminate\Support\Carbon $update_time 更新时间
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Margin\MarginTemplate[] $templates
 * @property-read int|null $templates_count
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Margin\MarginContract newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Margin\MarginContract newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Margin\MarginContract query()
 * @mixin \Eloquent
 */
class MarginContract extends EloquentModel
{
    protected $table = 'tb_sys_margin_contract';

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'contract_no',
        'customer_id',
        'product_id',
        'payment_ratio',
        'bond_template_id',
        'day',
        'status',
        'is_history_contract',
        'is_deleted',
        'create_time',
        'update_time',
    ];

    public function templates()
    {
        return $this->hasMany(MarginTemplate::class, 'contract_id', 'id')->where('is_del', YesNoEnum::NO);
    }
}
