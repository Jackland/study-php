<?php

namespace App\Models\Product;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Product\ProductCustomField
 * 
 * @property int $id
 * @property int $product_id 产品ID
 * @property int $type 类型 1Product Information  2Product Dimensions
 * @property string $name 自定义字段名
 * @property string $value 自定义字段值
 * @property int $sort 排序
 */
class ProductCustomField extends Model
{
    public $timestamps = false;

    protected $table = 'oc_product_custom_field';

    protected $dates = [
        
    ];

    protected $fillable = [
        'product_id',
        'type',
        'name',
        'value',
        'sort',
    ];
}
