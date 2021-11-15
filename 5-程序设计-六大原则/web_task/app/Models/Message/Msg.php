<?php

namespace App\Models\Message;

use Illuminate\Database\Eloquent\Model;

/**
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
 * @property int $operation_id 运营人员的ID
 * @property \Illuminate\Support\Carbon $create_time 创建时间
 * @property string $receiver_group_ids 按照联系组发站内信时有值，组Id逗号分隔，仅用作展示
 * @property-read \App\Models\Message\MsgContent $content
 * Class Msg
 * @package App\Models\Message
 */
class Msg extends Model
{
    protected $table = 'oc_msg';

    public $timestamps = false;

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function content()
    {
        return $this->hasOne(MsgContent::class, 'msg_id');
    }
}
