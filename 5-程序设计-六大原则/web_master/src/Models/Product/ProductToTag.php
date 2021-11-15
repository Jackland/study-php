<?php

namespace App\Models\Product;

use Framework\Model\EloquentModel;

/**
 * App\Models\Product\ProductToTag
 *
 * @property int $product_id 商品ID
 * @property int $tag_id 标签ID
 * @property int $is_sync_tag 是否是其他系统同步过来的标签，影响自身校验产生的标签是否会覆盖同步标签的修改
 * @property string|null $create_user_name 创建人
 * @property \Illuminate\Support\Carbon|null $create_time 创建时间
 * @property string|null $update_user_name 更新人
 * @property \Illuminate\Support\Carbon|null $update_time 更新时间
 * @property string|null $program_code 操作码
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\ProductToTag newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\ProductToTag newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\ProductToTag query()
 * @mixin \Eloquent
 */
class ProductToTag extends EloquentModel
{
    protected $table = 'oc_product_to_tag';
    protected $primaryKey = ''; // TODO 主键未知或大于1个

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'is_sync_tag',
        'create_user_name',
        'create_time',
        'update_user_name',
        'update_time',
        'program_code',
    ];
}
