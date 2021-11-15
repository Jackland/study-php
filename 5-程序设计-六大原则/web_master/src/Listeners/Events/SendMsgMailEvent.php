<?php

namespace App\Listeners\Events;

/**
 * 发送消息邮件
 * Class SendMsgMailEvent
 * @package App\Listeners\Events
 */
class SendMsgMailEvent
{
    public $msgId;
    public $receiverIds;

    public function __construct(int $msgId, array $receiverIds)
    {
        $this->msgId = $msgId;
        $this->receiverIds = $receiverIds;
    }
}
