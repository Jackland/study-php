<?php

namespace App\Models\Information;

use Framework\Model\EloquentModel;

/**
 * App\Models\Information\UploadInformation
 *
 * @property int $id 主键ID
 * @property string|null $file_name 文件名称
 * @property string|null $folder 文件夹名称
 * @property string|null $file_path 文件路径 (相对路径)
 * @property string|null $file_suffix 文件后缀
 * @property string|null $file_size 文件大小 ( 单位 K )
 * @property \Illuminate\Support\Carbon|null $created_time 创建时间
 * @property \Illuminate\Support\Carbon|null $updated_time 更新时间
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Information\UploadInformation newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Information\UploadInformation newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Information\UploadInformation query()
 * @mixin \Eloquent
 */
class UploadInformation extends EloquentModel
{
    protected $table = 'oc_upload_information';

    protected $dates = [
        'created_time',
        'updated_time',
    ];

    protected $fillable = [
        'file_name',
        'folder',
        'file_path',
        'file_suffix',
        'file_size',
        'created_time',
        'updated_time',
    ];
}
