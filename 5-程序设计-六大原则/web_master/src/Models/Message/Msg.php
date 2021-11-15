<?php

namespace App\Models\Message;

use App\Enums\Message\MsgMode;
use App\Models\Customer\Customer;
use Framework\Model\EloquentModel;

/**
 * App\Models\Message\Msg
 *
 * @property int $id ID
 * @property int $sender_id 发送客户ID 其中0为系统通知 -1位平台小助手
 * @property string $title 主题
 * @property int $receive_type 接收方类型 1用户 2平台小助手 3系统
 * @property int $msg_type 消息类型,1xx为Product类型(101:product_stock,102:product_review,103:product_approve),2xx为RMA类型,3xx为BID,4xx为Order类型(401:order_status),5xx为Other类型,7xx为Incoming Shipment入库单类型
 * @property int $parent_msg_id 父节点的消息ID
 * @property int $root_msg_id 回复的初始消息ID
 * @property int $msg_mode 1私聊 2群发
 * @property int $is_marked 0未收藏,1收藏
 * @property int $status 消息类型,1xx为Product类型(101:product_stock,102:product_review,103:product_approve),2xx为RMA类型,3xx为BID,4xx为Order类型(401:order_status),5xx为Other类型
 * @property int $is_sent 0未发送 1已发送
 * @property int $delete_status 0未删 1回收站 2删除
 * @property \Illuminate\Support\Carbon $create_time 创建时间
 * @property-read \App\Models\Message\MsgContent $content
 * @property-read string $msg_mode_name
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Message\MsgReceive[] $receives
 * @property-read \App\Models\Customer\Customer $sender
 * @property-read int|null $receives_count
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Message\Msg newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Message\Msg newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Message\Msg query()
 * @mixin \Eloquent
 * @property int $operation_id 运营人员的ID
 * @property string $receiver_group_ids 按照联系组发站内信时有值，组Id逗号分隔，仅用作展示
 * @property-read \App\Models\Message\Msg $rootMsg
 */
class Msg extends EloquentModel
{
    const KEY_LOW_INVENTORY_ALERT = 'low-inventory-alert';

    const PLATFORM_SECRETARY_SENDER_OR_RECEIVER_ID = -1;
    const SYSTEM_SENDER_ID = 0;

    protected $table = 'oc_msg';

    protected $dates = [
        'create_time',
    ];

    protected $fillable = [
        'sender_id',
        'receive_type',
        'title',
        'msg_type',
        'parent_msg_id',
        'root_msg_id',
        'msg_mode',
        'is_marked',
        'status',
        'is_sent',
        'delete_status',
        'create_time',
        'operation_id',
        'receiver_group_ids',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function content()
    {
        return $this->hasOne(MsgContent::class, 'msg_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function receives()
    {
        return $this->hasMany(MsgReceive::class, 'msg_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function sender()
    {
        return $this->belongsTo(Customer::class, 'sender_id', 'customer_id');
    }

    /**
     * @return string
     */
    public function getMsgModeNameAttribute(): string
    {
        return MsgMode::getViewItems()[$this->msg_mode] ?? '';
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function rootMsg()
    {
        return $this->belongsTo(Msg::class, 'root_msg_id');
    }
}
