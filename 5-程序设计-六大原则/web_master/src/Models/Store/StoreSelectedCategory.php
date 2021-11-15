<?php

namespace App\Models\Store;

use Framework\Model\EloquentModel;

/**
 * App\Models\Store\StoreSelectedCategory
 *
 * @property int $id          id 自增主键
 * @property int $customer_id seller的id
 * @property int $category_id 类目id
 * @property int $product_id  最新选择此类目的产品ID
 * @property int $update_num  更新次数
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Store\StoreSelectedCategory newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Store\StoreSelectedCategory newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Store\StoreSelectedCategory query()
 * @mixin \Eloquent
 * @property \Illuminate\Support\Carbon $create_time 创建时间
 * @property \Illuminate\Support\Carbon|null $update_time 更新时间
 */
class StoreSelectedCategory extends EloquentModel
{
    public const CREATED_AT = 'create_time';
    public const UPDATED_AT = 'update_time';

    protected $table = 'oc_store_selected_category';
    public $timestamps = true;

    protected $fillable = [
        'customer_id',
        'category_id',
        'product_id',
        'update_num',
    ];

}
