<?php

namespace App\Models\Product;

use Framework\Model\EloquentModel;

/**
 * App\Models\Product\ProductImportBatchErrorReport
 *
 * @property int $id 主键ID
 * @property int $customer_id seller的id
 * @property int $batch_id 产品导入批次的id
 * @property int $category_id 目录Id
 * @property string $mpn mpn
 * @property string $sold_separately Sold Separately（Yes/No）
 * @property string $not_sale_platform 不可销售平台
 * @property string $product_title Product Title
 * @property string $color Color
 * @property string $material Material
 * @property string $assemble_is_required Manual Required（Yes/No)
 * @property string $product_size Product Size
 * @property string $product_type Product Type
 * @property string $sub_items Sub-items
 * @property string $sub_items_quantity Sub-items Quantity
 * @property string $length Length（inch/cm)
 * @property string $width Width（inch/cm)
 * @property string $height Height（inch/cm)
 * @property string $weight Weight（inch/cm)
 * @property string $current_price Current Price
 * @property string $display_price Display Price（Invisible/Visible）
 * @property string $origin_design 是否原创
 * @property string $description 产品描述
 * @property string $extends_info 扩展信息(文件之类)
 * @property string $error_content 错误内容
 * @property \Illuminate\Support\Carbon $create_time 创建时间
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\ProductImportBatchErrorReport newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\ProductImportBatchErrorReport newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\ProductImportBatchErrorReport query()
 * @mixin \Eloquent
 */
class ProductImportBatchErrorReport extends EloquentModel
{
    protected $table = 'oc_product_import_batch_error_report';

    protected $dates = [
        'create_time',
    ];

    protected $fillable = [
        'customer_id',
        'batch_id',
        'category_id',
        'mpn',
        'sold_separately',
        'product_title',
        'color',
        'material',
        'assemble_is_required',
        'product_size',
        'product_type',
        'sub_items',
        'sub_items_quantity',
        'length',
        'width',
        'height',
        'weight',
        'current_price',
        'display_price',
        'origin_design',
        'description',
        'extends_info',
        'error_content',
        'create_time',
    ];
}
