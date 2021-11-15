<?php

namespace App\Models\Futures;

use App\Models\Customer\Customer;
use App\Models\Product\Product;
use Framework\Model\EloquentModel;

/**
 * \App\Models\Futures\FuturesMarginTemplate
 *
 * @property int $id 自增主键
 * @property int $seller_id SellerId
 * @property int $product_id 产品ID
 * @property float $buyer_payment_ratio buyer支付保证金比例 10.00% 填写 10.00
 * @property float $seller_payment_ratio seller支付保证金比例 10.00% 填写 10.00
 * @property bool $min_expected_storage_days 最小预估入库日期 最小为1
 * @property bool $max_expected_storage_days 最大预估入库日期 最大为90
 * @property bool $status 状态
 * @property bool $is_deleted 是否删除
 * @property bool $is_check_agreement 是否同意协议
 * @property string $create_time 创建时间
 * @property string $update_time 更新时间
 * @property-read \App\Models\Customer\Customer $buyer
 * @property-read \App\Models\Product\Product $product
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Futures\FuturesMarginOrderRelation[] $relateOrders
 * @property-read int|null $relate_orders_count
 * @property-read \App\Models\Customer\Customer $seller
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Futures\FuturesMarginTemplate newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Futures\FuturesMarginTemplate newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Futures\FuturesMarginTemplate query()
 * @mixin \Eloquent
 */
class FuturesMarginTemplate extends EloquentModel
{
    protected $table = 'oc_futures_margin_template';

    public function buyer()
    {
        return $this->belongsTo(Customer::class, 'buyer_id');
    }

    public function seller()
    {
        return $this->belongsTo(Customer::class, 'seller_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function relateOrders()
    {
        return $this->hasMany(FuturesMarginOrderRelation::class, 'agreement_id');
    }
}
