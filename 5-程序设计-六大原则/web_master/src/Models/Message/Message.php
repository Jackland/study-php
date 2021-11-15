<?php

namespace App\Models\Message;

use Framework\Model\EloquentModel;

/**
 * App\Models\Message\Message
 *
 * @property int $id
 * @property int $send_id 发送者的ID
 * @property int $receiver_id 接收者的ID
 * @property string $title
 * @property int $msg_type 消息类型,1xx为Product类型(101:product_stock,102:product_review,103:product_approve),2xx为RMA类型,3xx为BID,4xx为Order类型(401:order_status),5xx为Other类型
 * @property int $user_type 0为发送者,1为接受者
 * @property int $message_id message的ID
 * @property int $is_marked 0未收藏,1收藏
 * @property int $is_read 0未读 1已读
 * @property int $is_del 0未删除 1回收站，2已删除
 * @property \Illuminate\Support\Carbon $create_time 创建时间
 * @property \Illuminate\Support\Carbon $update_time 更新时间
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Message\Message newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Message\Message newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Message\Message query()
 * @mixin \Eloquent
 */
class Message extends EloquentModel
{
    protected $table = 'oc_message';

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'send_id',
        'receiver_id',
        'title',
        'msg_type',
        'user_type',
        'message_id',
        'is_marked',
        'is_read',
        'is_del',
        'create_time',
        'update_time',
    ];
}
