<?php

namespace App\Models\Message;

use App\Enums\Message\MsgReceiveReplied;
use App\Models\Customer\Customer;
use Framework\Model\EloquentModel;

/**
 * App\Models\Message\MsgReceive
 *
 * @property int $id ID
 * @property int $msg_id 消息oc_msg_id
 * @property int $receiver_id 收人ID -1位平台小助手
 * @property int $send_type 发送方类型 1用户 2平台小助手 3系统
 * @property int $is_marked 0未收藏,1收藏
 * @property int $is_read 0未读,1已读
 * @property int $replied_status 0未回复 1已回复 2不处理
 * @property int $delete_status 0未删 1回收站 2删除
 * @property \Illuminate\Support\Carbon $create_time 创建时间
 * @property-read string $replied_status_name
 * @property-read \App\Models\Message\Msg $msg
 * @property-read \App\Models\Customer\Customer $receiver
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Message\MsgReceive newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Message\MsgReceive newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Message\MsgReceive query()
 * @mixin \Eloquent
 */
class MsgReceive extends EloquentModel
{
    protected $table = 'oc_msg_receive';

    protected $dates = [
        'create_time',
    ];

    protected $fillable = [
        'msg_id',
        'receiver_id',
        'send_type',
        'is_marked',
        'is_read',
        'replied_status',
        'delete_status',
        'create_time',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function msg()
    {
        return $this->belongsTo(Msg::class, 'msg_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function receiver()
    {
        return $this->belongsTo(Customer::class, 'receiver_id', 'customer_id');
    }

    /**
     * @return string
     */
    public function getRepliedStatusNameAttribute(): string
    {
        return MsgReceiveReplied::getViewItems()[$this->replied_status] ?? '';
    }
}
