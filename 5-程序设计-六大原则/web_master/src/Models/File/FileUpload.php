<?php

namespace App\Models\File;

use Framework\Model\EloquentModel;

/**
 * \App\Models\File\FileUpload
 *
 * @property int $file_upload_id 主键file_id
 * @property string|null $path 文件路径(相对路径或者url路径，必须携带后缀)
 * @property string|null $name 文件名称(必须携带后缀)
 * @property string|null $suffix 文件后缀
 * @property int|null $size 文件大小
 * @property string|null $mime_type 文件类型
 * @property string|null $orig_name 文件上传名称(必须携带后缀)
 * @property string|null $date_added 添加时间
 * @property string|null $date_modified 修改时间
 * @property string|null $mark 备注信息
 * @property bool|null $status 状态（1:存续  0:已删除）
 * @property int|null $add_operator 添加用户id
 * @property int|null $del_operator 删除用户id
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\File\FileUpload newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\File\FileUpload newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\File\FileUpload query()
 * @mixin \Eloquent
 */
class FileUpload extends EloquentModel
{
    protected $table = 'oc_file_upload';

    protected $primaryKey = 'file_upload_id';
}
