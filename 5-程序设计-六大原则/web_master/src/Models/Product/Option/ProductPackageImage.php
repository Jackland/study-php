<?php

namespace App\Models\Product\Option;

use Framework\Model\EloquentModel;

/**
 * App\Models\Product\Option\ProductPackageImage
 *
 * @property int $product_package_image_id
 * @property int $product_id
 * @property string|null $image_name
 * @property string|null $image
 * @property string $origin_image_name 图片的原始文件名称
 * @property int|null $file_upload_id 文件上传id  可以指示文件相对路径 \r\n0  -  相对于productPackage/\r\n-1 - 相对于image/\r\n其他 相对于image/ 在oc_file_upload存在对应
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\Option\ProductPackageImage newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\Option\ProductPackageImage newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\Option\ProductPackageImage query()
 * @mixin \Eloquent
 */
class ProductPackageImage extends EloquentModel
{
    protected $table = 'oc_product_package_image';
    protected $primaryKey = 'product_package_image_id';

    protected $dates = [
        
    ];

    protected $fillable = [
        'product_id',
        'image_name',
        'image',
        'origin_image_name',
        'file_upload_id',
    ];
}
