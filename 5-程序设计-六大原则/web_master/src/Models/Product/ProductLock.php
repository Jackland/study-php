<?php

namespace App\Models\Product;

use App\Enums\Product\ProductLockType;
use App\Models\Futures\FuturesMarginAgreement;
use App\Models\Margin\MarginAgreement;
use Framework\Model\EloquentModel;

/**
 * App\Models\Product\ProductLock
 *
 * @property int $id 自增主键
 * @property int $product_id 产品ID
 * @property int $seller_id SellerId
 * @property int $agreement_id 协议ID
 * @property int $type_id 交易类型，type_id字典值维护在oc_setting表transaction_type
 * @property int $origin_qty 原始锁定数量
 * @property int $qty 剩余数量
 * @property int $parent_product_id 父产品Product
 * @property int|null $set_qty combo子产品配比
 * @property string|null $memo 备注
 * @property string|null $create_user_name 创建者
 * @property string|null $create_time 创建时间
 * @property string|null $update_user_name 修改者
 * @property string|null $update_time 修改时间
 * @property string|null $program_code 程序号
 * @property bool|null $is_ignore_qty 0-需要计算该产品的锁定库存 1-不需要计算该产品的锁定库存
 * @property-read \App\Models\Product\Product $product 对应的产品
 * @property-read \App\Models\Product\Product $sonProduct 对应产品可能的子产品
 * @property-read \App\Models\Margin\MarginAgreement $margin 对应的现货协议 type_id = 2时可用
 * @property-read \App\Models\Futures\FuturesMarginAgreement $futures 对应的现货协议 type_id = 2时可用
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\ProductLock newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\ProductLock newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\ProductLock query()
 * @mixin \Eloquent
 * @property-read int $quantity
 */
class ProductLock extends EloquentModel
{
    protected $table = 'oc_product_lock';

    /**
     * 对应的子产品，注意:这里可能对应的是combo的子产品
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function sonProduct()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * 对应的实际产品 可能和sonProduct一样
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function product()
    {
        return $this->belongsTo(Product::class, 'parent_product_id');
    }

    //

    /**
     * 关联的现货协议
     * 注意只有type_id=2时才能使用该模型
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function margin()
    {
        return $this->hasOne(MarginAgreement::class, 'id', 'agreement_id');
    }

    /**
     * 关联的期货协议
     * 注意只有type_id=3时才能使用该模型
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function futures()
    {
        return $this->hasOne(FuturesMarginAgreement::class, 'id', 'agreement_id');
    }

    /**
     * @return int
     */
    public function getQuantityAttribute()
    {
        return (int)($this->qty / $this->set_qty);
    }
}
