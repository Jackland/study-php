<?php

namespace App\Models\Attach;

use Framework\Model\EloquentModel;
use App\Components\Storage\StorageCloud;

/**
 * App\Models\Attach\FileUploadDetail
 *
 * @property int $id 自增主键ID
 * @property int $menu_id 资源目录ID
 * @property string $file_name 文件名称
 * @property string $file_path 文件路径 (相对路径)
 * @property int $delete_flag 是否删除 0 未删除  1 已删除
 * @property int $file_status 是否被使用 0 未使用 1 已使用 （未使用的资源后期会清理）
 * @property string|null $file_suffix 文件后缀
 * @property string|null $file_size 文件大小 ( 单位 K )
 * @property string $create_username 创建人
 * @property \Illuminate\Support\Carbon $create_time 创建时间
 * @property string $update_username 更新人
 * @property \Illuminate\Support\Carbon $update_time 更新时间
 * @property string|null $reserved_field 预留字段
 * @property-read mixed $full_file_path
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Attach\FileUploadDetail newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Attach\FileUploadDetail newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Attach\FileUploadDetail query()
 * @mixin \Eloquent
 */
class FileUploadDetail extends EloquentModel
{
    protected $table = 'tb_file_upload_detail';

    protected $appends = ['full_file_path', 'is_image'];

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
        'file_suffix',
        'file_size',
        'create_username',
        'create_time',
        'update_username',
        'update_time',
        'reserved_field',
    ];

    public function getFullFilePathAttribute()
    {
        return StorageCloud::root()->getUrl($this->attributes['file_path'] ?? '');
    }

    public function getIsImageAttribute()
    {
        $ext = ['ani', 'aiff', 'anim', 'apng', 'art', 'bmp', 'bpg', 'bsave', 'cal', 'cin', 'cpc', 'cpt', 'dds', 'dpx', 'ecw', 'exr', 'fits', 'flic', 'flif', 'fpx', 'gif', 'hdri', 'hevc', 'icer', 'icns', 'ico', 'ics', 'ilbm', 'jb2', 'jbig', 'jbig2', 'jng', 'jp2', 'jpc', 'jpeg', 'jpf', 'jpg', 'kra', 'mng', 'miff', 'nrrd', 'pam', 'pbm', 'pgm', 'ppm', 'pnm', 'pcx', 'pgf', 'pictor', 'png', 'psd', '', 'psb', 'psp', 'qtvr', 'ras', 'rgbe', 'swf', 'logluv', 'tiff', 'sgi', 'swc', 'tga', 'ufo', 'ufp', 'wbmp', 'webp', 'xbm', 'xcf', 'xpm', 'xwd'];
        if (in_array($this->attributes['file_suffix'], $ext)) {
            return true;
        } else {
            return false;
        }
    }
}
