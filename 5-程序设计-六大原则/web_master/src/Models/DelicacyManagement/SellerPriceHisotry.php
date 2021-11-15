<?php

namespace App\Models\DelicacyManagement;

use App\Models\Product\Product;
use App\Models\Product\ProductWeightConfig;
use Framework\Model\EloquentModel;

/**
 * App\Models\DelicacyManagement\SellerPriceHisotry
 *
 * @property int $id
 * @property int|null $price_id seller_price主键
 * @property int|null $product_id product主键
 * @property string|null $price 历史价格
 * @property \Illuminate\Support\Carbon|null $add_date 插入时间
 * @property int|null $status 操作成功 1 失败 2
 * @property-read \App\Models\Product\Product|null $product
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\DelicacyManagement\SellerPriceHisotry newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\DelicacyManagement\SellerPriceHisotry newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\DelicacyManagement\SellerPriceHisotry query()
 * @mixin \Eloquent
 * @property-read \App\Models\Product\ProductWeightConfig $productWeightConfig
 */
class SellerPriceHisotry extends EloquentModel
{
    protected $table = 'oc_seller_price_history';

    protected $dates = [
        'add_date',
    ];

    protected $fillable = [
        'price_id',
        'product_id',
        'price',
        'add_date',
        'status',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function productWeightConfig()
    {
        return $this->hasOne(ProductWeightConfig::class, 'product_id', 'product_id');
    }
}
