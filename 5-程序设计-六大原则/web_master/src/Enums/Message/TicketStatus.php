<?php

namespace App\Enums\Message;

use Framework\Enum\BaseEnum;

/**
 * oc_ticket --> status
 *
 * Class TicketStatus
 * @package App\Enums\Message
 */
class TicketStatus extends BaseEnum
{
    const WAIT_RECEIVE = 1; // 待领取
    const WAIT_PROCESS = 2; // 已领取-待处理
    const PROCESSED = 3; // 已处理
    const BEING_PROCESSED = 4; //处理中
    const IGNORE = 5; // 忽略

    // 获取应该重置处理客服的状态 -- 如果原来客服账号已关闭
    public static function getResetProcessAdminStatus()
    {
        return [
            self::WAIT_PROCESS,
            self::PROCESSED,
            self::BEING_PROCESSED,
            self::IGNORE
        ];
    }

    // 获取应该重置为处理中的状态
    public static function getToBeingProcessedStatus()
    {
        return [
            self::PROCESSED,
            self::IGNORE
        ];
    }

}