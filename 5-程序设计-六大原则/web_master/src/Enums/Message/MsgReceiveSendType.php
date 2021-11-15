<?php

namespace App\Enums\Message;

use App\Models\Message\Msg;
use Framework\Enum\BaseEnum;

class MsgReceiveSendType extends BaseEnum
{
    const USER = 1;
    const PLATFORM_SECRETARY = 2;
    const SYSTEM = 3;

    /**
     * @param int $senderId
     * @return int
     */
    public static function getSenderType(int $senderId): int
    {
        switch ($senderId) {
            case Msg::PLATFORM_SECRETARY_SENDER_OR_RECEIVER_ID:
                $sendType = self::PLATFORM_SECRETARY;
                break;
            case Msg::SYSTEM_SENDER_ID:
                $sendType = self::SYSTEM;
                break;
            default:
                $sendType = self::USER;
        }

        return $sendType;
    }
}
