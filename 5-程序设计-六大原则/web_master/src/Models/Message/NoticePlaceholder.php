<?php

namespace App\Models\Message;

use Framework\Model\EloquentModel;

/**
 * App\Models\Message\NoticePlaceholder
 *
 * @property int $placeholder_id
 * @property int $notice_id
 * @property int $customer_id
 * @property int|null $is_read 已读1，未读0
 * @property int $make_sure_status 是否确认
 * @property string|null $memo 备注
 * @property string|null $create_user_name 创建人
 * @property \Illuminate\Support\Carbon|null $create_time 创建时间
 * @property string|null $update_user_name 更新人
 * @property \Illuminate\Support\Carbon|null $update_time 更新时间
 * @property string|null $program_code 程序号
 * @property int $is_marked 0未收藏,1已收藏
 * @property int $is_del 0未删除,1已删除
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Message\NoticePlaceholder newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Message\NoticePlaceholder newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Message\NoticePlaceholder query()
 * @mixin \Eloquent
 */
class NoticePlaceholder extends EloquentModel
{
    protected $table = 'tb_sys_notice_placeholder';
    protected $primaryKey = 'placeholder_id';

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'notice_id',
        'customer_id',
        'is_read',
        'make_sure_status',
        'memo',
        'create_user_name',
        'create_time',
        'update_user_name',
        'update_time',
        'program_code',
        'is_marked',
        'is_del',
    ];
}
