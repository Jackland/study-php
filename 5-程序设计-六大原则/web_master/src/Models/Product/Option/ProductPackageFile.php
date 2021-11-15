<?php

namespace App\Models\Product\Option;

use Framework\Model\EloquentModel;

/**
 * App\Models\Product\Option\ProductPackageFile
 *
 * @property int $product_package_file_id
 * @property int $product_id
 * @property string|null $file_name
 * @property string|null $file
 * @property string $origin_file_name 文件的原始名称
 * @property int|null $file_upload_id 文件上传id  可以指示文件相对路径 \r\n0  -  相对于productPackage/\r\n-1 - 相对于image/\r\n其他 相对于image/ 在oc_file_upload存在对应\r\n
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\Option\ProductPackageFile newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\Option\ProductPackageFile newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\Option\ProductPackageFile query()
 * @mixin \Eloquent
 */
class ProductPackageFile extends EloquentModel
{
    protected $table = 'oc_product_package_file';
    protected $primaryKey = 'product_package_file_id';

    protected $dates = [
        
    ];

    protected $fillable = [
        'product_id',
        'file_name',
        'file',
        'origin_file_name',
        'file_upload_id',
    ];
}
