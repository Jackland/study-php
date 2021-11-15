<?php

namespace App\Models\Product;

use Framework\Model\EloquentModel;

/**
 * App\Models\Product\ProductImportBatch
 *
 * @property int $id 主键ID
 * @property int $customer_id seller的id
 * @property string $file_name 文件名称
 * @property int $type 1批量导入，2批量修改
 * @property int $product_count 导入的产品数量
 * @property int $product_error_count 导入的产品错误数量
 * @property \Illuminate\Support\Carbon $create_time 创建时间
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\ProductImportBatch newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\ProductImportBatch newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\ProductImportBatch query()
 * @mixin \Eloquent
 */
class ProductImportBatch extends EloquentModel
{
    protected $table = 'oc_product_import_batch';

    protected $dates = [
        'create_time',
    ];

    protected $fillable = [
        'customer_id',
        'file_name',
        'type',
        'product_count',
        'product_error_count',
        'create_time',
    ];
}
