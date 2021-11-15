<?php

namespace App\Enums\Message;

use Framework\Enum\BaseEnum;

class MsgReceiveReplied extends BaseEnum
{
    const NO_REPLY = 0;
    const REPLIED = 1;
    const NOT_HANDLE = 2;

    /**
     * @return array|string[]
     */
    public static function getViewItems(): array
    {
        return [
            self::NO_REPLY => 'Not Replied',
            self::REPLIED => 'Replied',
            self::NOT_HANDLE => 'Ignore',
        ];
    }
}
