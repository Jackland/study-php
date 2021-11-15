<?php

namespace App\Models\File;

use Framework\Model\EloquentModel;

/**
 * App\Models\File\CustomerFileManage
 *
 * @property int $id ID
 * @property int $customer_id customer_id
 * @property string $name 文件名称
 * @property int $menu_id tb_file_upload_menu.ID
 * @property int $parent_id 父节点ID
 * @property string $file_path 文件路径 (相对路径)
 * @property int $file_type  1目录,2图片类型,3文档类型
 * @property string $file_suffix 文件后缀
 * @property string $file_size 文件大小 ( 单位 K )
 * @property int $is_del 是否删除,0未删除，1已删除
 * @property \Illuminate\Support\Carbon $create_time 创建时间
 * @property \Illuminate\Support\Carbon $update_time 更新时间
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\File\CustomerFileManage newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\File\CustomerFileManage newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\File\CustomerFileManage query()
 * @mixin \Eloquent
 * @property bool $is_dir  1目录,0文件
 */
class CustomerFileManage extends EloquentModel
{
    protected $table = 'oc_customer_file_manage';

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'id',
        'customer_id',
        'name',
        'menu_id',
        'parent_id',
        'file_path',
        'file_type',
        'file_suffix',
        'file_size',
        'is_del',
        'create_time',
        'update_time',
    ];
}
