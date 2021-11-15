<?php

namespace App\Models\Message;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id ID
 * @property int $msg_id 消息oc_msg_id
 * @property string|null $content 内容
 * @property string $attach 附件
 * @property int $attach_id 附件ID
 *
 * Class MsgContent
 * @package App\Models\Message
 */
class MsgContent extends Model
{
    protected $table = 'oc_msg_content';
}
