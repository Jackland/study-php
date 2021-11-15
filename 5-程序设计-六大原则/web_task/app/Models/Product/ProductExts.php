<?php

namespace App\Models\Product;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Product\ProductExts
 *
 * @property int $id 主键
 * @property int|null $product_id 产品id
 * @property string|null $sku sku
 * @property \Illuminate\Support\Carbon|null $receive_date 收货日期
 * @property string|null $create_user_name 创建人
 * @property \Illuminate\Support\Carbon|null $create_time 创建时间
 * @property string|null $update_user_name 更新人
 * @property \Illuminate\Support\Carbon|null $update_time 更新时间
 * @property string|null $program_code 程序号
 * @mixin \Eloquent
 * @property bool $is_original_design 产品专利标识 0：无，1：有
 * @property int $is_customize 定制化标识 0否 1是
 * @property string $origin_place_code 原产地code,oc_country的iso_code_3
 * @property string $filler 填充物
 * @property string|null $assemble_length 组装长度 -1.00 代表不适用
 * @property string|null $assemble_width 组装宽度 -1.00 代表不适用
 * @property string|null $assemble_height 组装高度 -1.00 代表不适用
 * @property string|null $assemble_weight 组装重量 -1.00 代表不适用
 */
class ProductExts extends Model
{
    public $timestamps = false;

    protected $table = 'oc_product_exts';

    protected $dates = [
        'receive_date',
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'product_id',
        'sku',
        'receive_date',
        'create_user_name',
        'create_time',
        'update_user_name',
        'update_time',
        'program_code',
        'is_original_design',
        'is_customize',
        'origin_place_code',
        'filler',
        'assemble_length',
        'assemble_width',
        'assemble_height',
        'assemble_weight',
    ];
}
