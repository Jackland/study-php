<?php

namespace App\Models\Message;

use Framework\Model\EloquentModel;

/**
 * App\Models\Message\NoticeToObject
 *
 * @property int $id 自增主键
 * @property int|null $notice_id 用户公告表(tb_sys_notice)的id
 * @property int|null $notice_object_id 用户对象的（tb_sys_notice_object）id
 * @property string|null $program_code 程序号
 * @property string|null $memo 备注
 * @property string|null $create_user_name 创建者
 * @property \Illuminate\Support\Carbon|null $create_time 创建时间
 * @property string|null $update_user_name 修改者
 * @property \Illuminate\Support\Carbon|null $update_time 修改时间
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Message\NoticeToObject newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Message\NoticeToObject newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Message\NoticeToObject query()
 * @mixin \Eloquent
 */
class NoticeToObject extends EloquentModel
{
    protected $table = 'tb_sys_notice_to_object';

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'notice_id',
        'notice_object_id',
        'program_code',
        'memo',
        'create_user_name',
        'create_time',
        'update_user_name',
        'update_time',
    ];
}
