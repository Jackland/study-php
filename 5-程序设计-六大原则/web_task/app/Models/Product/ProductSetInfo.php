<?php

namespace App\Models\Product;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Product\ProductSetInfo
 *
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
 * @property float|null $price
 * @property string|null $status
 * @property int $id
 * @property int|null $is_edit
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\ProductSetInfo newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\ProductSetInfo newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\ProductSetInfo query()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\ProductSetInfo whereCubes($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\ProductSetInfo whereHeadImageUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\ProductSetInfo whereHeight($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\ProductSetInfo whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\ProductSetInfo whereIsEdit($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\ProductSetInfo whereItemNo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\ProductSetInfo whereLength($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\ProductSetInfo whereMpn($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\ProductSetInfo whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\ProductSetInfo wherePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\ProductSetInfo whereProductId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\ProductSetInfo whereQty($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\ProductSetInfo whereSellerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\ProductSetInfo whereSetMpn($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\ProductSetInfo whereSetProductId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\ProductSetInfo whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\ProductSetInfo whereWeight($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\ProductSetInfo whereWidth($value)
 * @mixin \Eloquent
 */
class ProductSetInfo extends Model
{
    protected $table = 'tb_sys_product_set_info';
    public $timestamps = false;
}