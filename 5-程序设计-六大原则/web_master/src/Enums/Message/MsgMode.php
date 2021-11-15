<?php

namespace App\Enums\Message;

use Framework\Enum\BaseEnum;

class MsgMode extends BaseEnum
{
    const PRIVATE_CHAT = 1;
    const MASS = 2;


    /**
     * @return array|string[]
     */
    public static function getViewItems(): array
    {
        return [
            self::PRIVATE_CHAT => 'Private Message',
            self::MASS => 'Group Message',
        ];
    }
}
