<?php

namespace App\Models\Link;

use Framework\Model\Eloquent\Builder;
use Framework\Model\EloquentModel;

/**
 * App\Models\Link\ProductToTag
 *
 * @property int $product_id 商品ID
 * @property int $tag_id 标签ID
 * @property int $is_sync_tag 是否是其他系统同步过来的标签，影响自身校验产生的标签是否会覆盖同步标签的修改
 * @property string|null $create_user_name 创建人
 * @property string|null $create_time 创建时间
 * @property string|null $update_user_name 更新人
 * @property string|null $update_time 更新时间
 * @property string|null $program_code 操作码
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Link\ProductToTag newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Link\ProductToTag newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Link\ProductToTag query()
 * @mixin \Eloquent
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Link\ProductToTag isLTL($productId)
 */
class ProductToTag extends EloquentModel
{
    protected $table = 'oc_product_to_tag';
    protected $primaryKey = '';

    protected $fillable = [
        'product_id',
        'tag_id',
        'is_sync_tag',
        'create_user_name',
        'create_time',
        'update_user_name',
        'update_time',
        'program_code',
    ];

    /**
     * Get the value indicating whether the IDs are incrementing.
     *
     * @return bool
     */
    public function getIncrementing()
    {
        return false;
    }

    /**
     * 是否是ltl
     * @param Builder $builder
     * @param int $productId
     * @return bool
     */
    public function scopeIsLTL(Builder $builder, int $productId)
    {
        return $builder->where('tag_id', 1)->where('product_id', $productId)->exists();
    }
}
