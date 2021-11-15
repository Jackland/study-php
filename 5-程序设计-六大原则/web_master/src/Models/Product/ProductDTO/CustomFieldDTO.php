<?php

namespace App\Models\Product\ProductDTO;

use Illuminate\Support\Fluent;

/**
 * 自定义字段
 * @property-read string $name 字段名
 * @property-read string $value 值
 * @property-read int $sort 排序
 */
class CustomFieldDTO extends Fluent
{

}
