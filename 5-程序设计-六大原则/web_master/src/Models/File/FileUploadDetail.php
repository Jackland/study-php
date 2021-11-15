<?php

namespace App\Models\File;

use Framework\Model\EloquentModel;

/**
 * App\Models\SellerBill\FileUploadDetail
 *
 * @property int $id 自增主键ID
 * @property int $menu_id 资源目录ID
 * @property string $file_name 文件名称
 * @property string $file_path 文件路径 (相对路径)
 * @property int $delete_flag 是否硬性删除
 * @property int $file_status 是否被使用 0 未使用 1 已使用 （未使用的资源后期会清理）
 * @property string $create_username 创建人
 * @property \Illuminate\Support\Carbon $create_time 创建时间
 * @property string $update_username 更新人
 * @property \Illuminate\Support\Carbon $update_time 更新时间
 * @property string|null $file_suffix 文件后缀
 * @property float|null $file_size 文件大小 ( 单位 K )
 * @property string|null $reserved_field 预留字段
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\File\FileUploadDetail newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\File\FileUploadDetail newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\File\FileUploadDetail query()
 * @mixin \Eloquent
 * @property string|null $file_width_height 文件为图片时的宽高
 */
class FileUploadDetail extends EloquentModel
{
    protected $table = 'tb_file_upload_detail';

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'menu_id',
        'file_name',
        'file_path',
        'delete_flag',
        'file_status',
        'create_username',
        'create_time',
        'update_username',
        'update_time',
    ];
}
