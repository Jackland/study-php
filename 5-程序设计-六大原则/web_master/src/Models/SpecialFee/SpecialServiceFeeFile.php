<?php

namespace App\Models\SpecialFee;

use Framework\Model\EloquentModel;

/**
 * App\Models\SpecialServiceFeeFile
 *
 * @property int $id 自增主键
 * @property string|null $header_id 头表ID
 * @property string|null $file_name 文件名称
 * @property int|null $file_size 文件大小
 * @property string|null $file_path 文件路径 (相对路径)
 * @property int|null $delete_flag 0:未删除 1:已删除
 * @property string|null $memo 备注
 * @property string|null $create_user_name 创建者
 * @property \Illuminate\Support\Carbon|null $create_time 创建时间
 * @property string|null $update_user_name 修改者
 * @property \Illuminate\Support\Carbon|null $update_time 修改时间
 * @property string|null $program_code 程序号
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SpecialFee\SpecialServiceFeeFile newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SpecialFee\SpecialServiceFeeFile newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SpecialFee\SpecialServiceFeeFile query()
 * @mixin \Eloquent
 */
class SpecialServiceFeeFile extends EloquentModel
{
    protected $table = 'tb_special_service_fee_file';

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'header_id',
        'file_name',
        'file_size',
        'file_path',
        'delete_flag',
        'memo',
        'create_user_name',
        'create_time',
        'update_user_name',
        'update_time',
        'program_code',
    ];
}
