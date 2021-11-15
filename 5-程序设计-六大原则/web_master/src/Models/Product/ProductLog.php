<?php

namespace App\Models\Product;

use Framework\Model\EloquentModel;

/**
 * App\Models\Product\ProductLog
 *
 * @property int $id
 * @property int $product_id 产品ID
 * @property int $seller_id sellerId
 * @property string $item_code 冗余产品item code
 * @property string $mpn 冗余产品mpn
 * @property int $type 类型 1-产品下架
 * @property string $before_data 修改前的数据记录，根据类型不同自行调整
 * @property string $modified_data 修改后的数据记录，根据类型不同自行调整
 * @property string|null $reason 原因
 * @property string|null $create_user 添加人
 * @property \Carbon\Carbon $created_at
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\ProductLog newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\ProductLog newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\ProductLog query()
 * @mixin \Eloquent
 */
class ProductLog extends EloquentModel
{
    protected $table = 'oc_product_log';

    protected $dates = [

    ];

    protected $fillable = [
        'product_id',
        'seller_id',
        'item_code',
        'mpn',
        'type',
        'before_data',
        'modified_data',
        'reason',
        'create_user',
    ];
}
