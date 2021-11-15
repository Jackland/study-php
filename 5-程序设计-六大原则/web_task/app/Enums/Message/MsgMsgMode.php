<?php

namespace App\Enums\Message;

use App\Enums\BaseEnum;

/**
 * oc_msg -> msg_mode
 * 群发OR私聊
 *
 * Class MsgMsgType
 * @package App\Enums\Message
 */
class MsgMsgMode extends BaseEnum
{
    const PRIVATE_CHAT = 1; // 私聊
    const MASS_TEXTING = 2; // 群发
}