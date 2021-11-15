<?php

namespace App\Models\Message;

use Framework\Model\EloquentModel;

/**
 * App\Models\Message\NoticeObject
 *
 * @property int $id 主键ID
 * @property int|null $identity 身份 1:seller,0:buyer
 * @property int|null $country_id 国别Id 0:all
 * @property string|null $name 对象名
 * @property string|null $memo 备注
 * @property string|null $create_user_name 创建者
 * @property \Illuminate\Support\Carbon|null $create_time 创建时间
 * @property string|null $update_user_name 修改者
 * @property \Illuminate\Support\Carbon|null $update_time 修改时间
 * @property string|null $program_code 程序号
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Message\NoticeObject newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Message\NoticeObject newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Message\NoticeObject query()
 * @mixin \Eloquent
 */
class NoticeObject extends EloquentModel
{
    protected $table = 'tb_sys_notice_object';

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'identity',
        'country_id',
        'name',
        'memo',
        'create_user_name',
        'create_time',
        'update_user_name',
        'update_time',
        'program_code',
    ];
}
