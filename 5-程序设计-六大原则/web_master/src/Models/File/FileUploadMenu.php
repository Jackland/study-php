<?php

namespace App\Models\File;

use Framework\Model\EloquentModel;

/**
 * App\Models\File\FileUploadMenu
 *
 * @property int $id 自增主键ID
 * @property bool $status 启用状态 0:禁用 1:启用
 * @property string|null $resource_path 资源存储路径
 * @property int $active_number 有效文件数量
 * @property string $create_username 创建人
 * @property \Illuminate\Support\Carbon $create_time 创建时间
 * @property string $update_username 更新人
 * @property \Illuminate\Support\Carbon $update_time 更新时间
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\File\FileUploadMenu[] $details
 * @property-read int|null $details_count
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\File\FileUploadMenu newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\File\FileUploadMenu newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\File\FileUploadMenu query()
 * @mixin \Eloquent
 */
class FileUploadMenu extends EloquentModel
{
    protected $table = 'tb_file_upload_menu';

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'status',
        'resource_path',
        'active_number',
        'create_username',
        'create_time',
        'update_username',
        'update_time',
    ];

    public function details()
    {
        return $this->hasMany(FileUploadDetail::class, 'menu_id');
    }
}
