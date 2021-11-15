<?php

namespace App\Models\Message;

use Framework\Model\EloquentModel;

/**
 * App\Models\Message\MsgContent
 *
 * @property int $id ID
 * @property int $msg_id 消息oc_msg_id
 * @property string|null $content 内容
 * @property string $attach 附件
 * @property int $attach_id 附件ID
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Message\MsgContent newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Message\MsgContent newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Message\MsgContent query()
 * @mixin \Eloquent
 */
class MsgContent extends EloquentModel
{
    protected $table = 'oc_msg_content';

    protected $dates = [
        
    ];

    protected $fillable = [
        'msg_id',
        'content',
        'attach',
        'attach_id',
    ];
}
