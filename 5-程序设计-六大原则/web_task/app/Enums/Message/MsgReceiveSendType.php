<?php

namespace App\Enums\Message;

use App\Enums\BaseEnum;

/**
 * oc_msg_receive -> send_type
 * 接收方类型
 *
 * Class MsgMsgType
 * @package App\Enums\Message
 */
class MsgReceiveSendType extends BaseEnum
{
    const CUSTOMER = 1; // 用户
    const PLATFORM_SECRETARY = 2; // 平台小助手
    const SYSTEM = 3; // 系统
}