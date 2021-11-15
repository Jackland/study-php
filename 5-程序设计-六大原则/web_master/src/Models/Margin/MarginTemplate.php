<?php

namespace App\Models\Margin;

use App\Models\Customer\Customer;
use App\Models\Product\Product;
use Framework\Model\EloquentModel;

/**
 * App\Models\Margin\MarginTemplate
 *
 * @property int $id
 * @property int $contract_id 合约ID
 * @property string $template_id 日期+6位递增序列的模板ID
 * @property int $bond_template_id 对应tb_bond_template表的ID
 * @property int $seller_id seller用户ID
 * @property int $product_id 商品ID
 * @property string $price 保证金货值价格
 * @property string $hp_price 废弃字段，此字段在上门取货二期需求上线时，定义与price字段相同
 * @property string $payment_ratio 支付比例，20.99%记作20.99
 * @property int $day 售卖天数
 * @property int $max_num 最高售卖数量
 * @property int $min_num 最低售卖数量
 * @property int $is_default 是否默认
 * @property int $is_del 是否删除
 * @property \Illuminate\Support\Carbon $create_time 创建时间
 * @property int $create_user 创建者
 * @property \Illuminate\Support\Carbon $update_time 更新时间
 * @property int $update_user 最新操作者
 * @property string|null $program_code 版本号
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Margin\MarginTemplate newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Margin\MarginTemplate newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Margin\MarginTemplate query()
 * @mixin \Eloquent
 * @property-read \App\Models\Product\Product $product
 * @property-read \App\Models\Customer\Customer $seller
 */
class MarginTemplate extends EloquentModel
{
    protected $table = 'tb_sys_margin_template';

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'contract_id',
        'template_id',
        'bond_template_id',
        'seller_id',
        'product_id',
        'price',
        'hp_price',
        'payment_ratio',
        'day',
        'max_num',
        'min_num',
        'is_default',
        'is_del',
        'create_time',
        'create_user',
        'update_time',
        'update_user',
        'program_code',
    ];

    public function seller()
    {
        return $this->belongsTo(Customer::class, 'seller_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
