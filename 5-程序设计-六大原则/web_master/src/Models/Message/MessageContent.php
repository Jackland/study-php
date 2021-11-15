<?php

namespace App\Models\Message;

use Framework\Model\EloquentModel;

/**
 * App\Models\Message\MessageContent
 *
 * @property int $id
 * @property int $msg_type 消息类型,1xx为Product类型,2xx为RMA类型
 * @property string $title
 * @property string|null $title_hash title的MD5值
 * @property string|null $content
 * @property string|null $content_hash content的MD5值
 * @property string $attach 附件
 * @property int $parent_id 父节点的ID
 * @property int $status 消息类型,1xx为Product类型(101:product_stock,102:product_review,103:product_approve),2xx为RMA类型,3xx为BID,4xx为Order类型(401:order_status),5xx为Other类型
 * @property \Illuminate\Support\Carbon $create_time 创建时间
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Message\MessageContent newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Message\MessageContent newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Message\MessageContent query()
 * @mixin \Eloquent
 */
class MessageContent extends EloquentModel
{
    protected $table = 'oc_message_content';

    protected $dates = [
        'create_time',
    ];

    protected $fillable = [
        'msg_type',
        'title',
        'title_hash',
        'content',
        'content_hash',
        'attach',
        'parent_id',
        'status',
        'create_time',
    ];
}
