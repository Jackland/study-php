<?php

namespace App\Models\Product\Option;

use Framework\Model\EloquentModel;

/**
 * App\Models\Product\Option\ProductPackageVideo
 *
 * @property int $product_package_video_id
 * @property int $product_id
 * @property string|null $video_name
 * @property string|null $video
 * @property string $origin_video_name 视频的原始名称
 * @property int|null $file_upload_id 文件上传id  可以指示文件相对路径 \r\n0  -  相对于productPackage/\r\n-1 - 相对于image/\r\n其他 相对于image/ 在oc_file_upload存在对应
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\Option\ProductPackageVideo newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\Option\ProductPackageVideo newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\Option\ProductPackageVideo query()
 * @mixin \Eloquent
 */
class ProductPackageVideo extends EloquentModel
{
    protected $table = 'oc_product_package_video';
    protected $primaryKey = 'product_package_video_id';

    protected $dates = [
        
    ];

    protected $fillable = [
        'product_id',
        'video_name',
        'video',
        'origin_video_name',
        'file_upload_id',
    ];
}
