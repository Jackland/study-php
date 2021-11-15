<?php

namespace App\Models\Message;

use Framework\Model\EloquentModel;

/**
 * App\Models\Message\TicketMessage
 *
 * @property int $id
 * @property int $ticket_id ticket表的主键id
 * @property int $create_admin_id 管理员id，管理员回复则有值
 * @property int $create_customer_id 来源ticket表
 * @property string|null $description
 * @property string|null $attachments 可上传多个文件，内容格式 数组-JSON [{"name":"", "url":""}]
 * @property string|null $comments
 * @property string|null $inner_attachments
 * @property \Illuminate\Support\Carbon $date_added
 * @property \Illuminate\Support\Carbon $date_read 阅读时间，即打开消息后的更新时间
 * @property int $is_read 0未读 1已读
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Message\TicketMessage newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Message\TicketMessage newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Message\TicketMessage query()
 * @mixin \Eloquent
 */
class TicketMessage extends EloquentModel
{
    protected $table = 'oc_ticket_message';

    protected $dates = [
        'date_added',
        'date_read',
    ];

    protected $fillable = [
        'ticket_id',
        'create_admin_id',
        'create_customer_id',
        'description',
        'attachments',
        'comments',
        'inner_attachments',
        'date_added',
        'date_read',
        'is_read',
    ];
}
