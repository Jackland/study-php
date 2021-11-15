<?php

namespace App\Models\Product;

use Framework\Model\EloquentModel;

/**
 * App\Models\Product\ProductFee
 *
 * @property int $id
 * @property int $product_id oc_product表主键
 * @property int $type 费用类型,详情看tb_sys_dictionary 表 DicCategory=PRODUCT_FEE_TYPE。
 * @property string $fee 费用
 * @property \Illuminate\Support\Carbon|null $create_time
 * @property \Illuminate\Support\Carbon|null $update_time
 * @property-read \App\Models\Product\Product $product
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\ProductFee newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\ProductFee newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\ProductFee query()
 * @mixin \Eloquent
 */
class ProductFee extends EloquentModel
{
    protected $table = 'oc_product_fee';

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'product_id',
        'type',
        'fee',
        'create_time',
        'update_time',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
