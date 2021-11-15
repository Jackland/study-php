<?php

namespace App\Models\Message;

use Framework\Model\EloquentModel;

/**
 * App\Models\Message\Notice
 *
 * @property int $id 自增主键
 * @property string|null $title 公告主题
 * @property int|null $type_id 公告类型ID 1：产品调整 2：系统更新 3：平台政策
 * @property int|null $top_status 是否置顶
 * @property int|null $make_sure_status 是否需要确认
 * @property int|null $publish_status 发布状态
 * @property \Illuminate\Support\Carbon|null $publish_date 发布时间
 * @property \Illuminate\Support\Carbon|null $effective_time 有效时间
 * @property string|null $content 公告内容
 * @property string|null $memo 备注
 * @property string|null $create_user_name 创建者
 * @property \Illuminate\Support\Carbon|null $create_time 创建时间
 * @property string|null $update_user_name 修改者
 * @property \Illuminate\Support\Carbon|null $update_time 修改时间
 * @property string|null $program_code 程序号
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Message\Notice newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Message\Notice newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Message\Notice query()
 * @mixin \Eloquent
 */
class Notice extends EloquentModel
{
    protected $table = 'tb_sys_notice';

    protected $dates = [
        'publish_date',
        'effective_time',
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'title',
        'type_id',
        'top_status',
        'make_sure_status',
        'publish_status',
        'publish_date',
        'effective_time',
        'content',
        'memo',
        'create_user_name',
        'create_time',
        'update_user_name',
        'update_time',
        'program_code',
    ];

    public function placeholder()
    {
        return $this->hasMany(NoticePlaceholder::class);
    }

    public function toObject()
    {
        return $this->hasMany(NoticeToObject::class);
    }
}
