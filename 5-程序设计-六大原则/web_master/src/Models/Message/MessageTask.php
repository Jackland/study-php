<?php

namespace App\Models\Message;

use Framework\Model\EloquentModel;

/**
 * App\Models\Message\MessageTask
 *
 * @property int $message_task_id
 * @property int $sender 发送者
 * @property string|null $receiver 接收对象 如：xx,yy,zz
 * @property int $msg_type 延用oc_message此字段标识
 * @property string $title 消息标题
 * @property string $title_hash 消息标题HASH值
 * @property string|null $content 消息内容
 * @property string $content_hash 消息内容HASH值
 * @property string $attach 附件
 * @property int $is_batch 0:非批量 1:批量
 * @property int $status 0:待处理 1:已处理
 * @property \Illuminate\Support\Carbon $create_time
 * @property \Illuminate\Support\Carbon $update_time
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Message\MessageTask newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Message\MessageTask newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Message\MessageTask query()
 * @mixin \Eloquent
 */
class MessageTask extends EloquentModel
{
    protected $table = 'oc_message_task';
    protected $primaryKey = 'message_task_id';

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'sender',
        'receiver',
        'msg_type',
        'title',
        'title_hash',
        'content',
        'content_hash',
        'attach',
        'is_batch',
        'status',
        'create_time',
        'update_time',
    ];
}
