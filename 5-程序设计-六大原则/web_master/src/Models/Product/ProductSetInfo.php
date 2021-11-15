<?php

namespace App\Models\Product;

use Framework\Model\EloquentModel;

/**
 * App\Models\Product\ProductSetInfo
 *
 * @property int $id
 * @property string $set_mpn 子MPN
 * @property float $weight 重量
 * @property float $cubes
 * @property string|null $head_image_url 主图路径
 * @property float $height 高
 * @property string|null $item_no
 * @property float $length 长
 * @property string|null $name 名称
 * @property int $qty 数量
 * @property float $width 宽
 * @property string $mpn 主MPN
 * @property int|null $seller_id SellerId
 * @property int $product_id productId
 * @property int|null $set_product_id 子产品的productId
 * @property string|null $price
 * @property string|null $status
 * @property int|null $is_edit
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\ProductSetInfo newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\ProductSetInfo newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\ProductSetInfo query()
 * @mixin \Eloquent
 * @property-read \App\Models\Product\Product $product
 * @property-read \App\Models\Product\Product $setProduct
 */
class ProductSetInfo extends EloquentModel
{
    protected $table = 'tb_sys_product_set_info';

    protected $fillable = [
        'set_mpn',
        'weight',
        'cubes',
        'head_image_url',
        'height',
        'item_no',
        'length',
        'name',
        'qty',
        'width',
        'mpn',
        'seller_id',
        'product_id',
        'set_product_id',
        'price',
        'status',
        'is_edit',
    ];

    public function product()
    {
        return $this->hasOne(Product::class, 'product_id', 'product_id');
    }

    public function setProduct()
    {
        return $this->hasOne(Product::class, 'product_id', 'set_product_id');
    }
}
