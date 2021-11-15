<?php

namespace App\Enums\Message;

use App\Enums\BaseEnum;

/**
 * oc_msg -> receive_type
 * 接收方类型
 *
 * Class MsgMsgType
 * @package App\Enums\Message
 */
class MsgReceiveType extends BaseEnum
{
    const CUSTOMER = 1; // 用户
    const PLATFORM_SECRETARY = 2; // 平台小助手
    const SYSTEM = 3; // 系统
}