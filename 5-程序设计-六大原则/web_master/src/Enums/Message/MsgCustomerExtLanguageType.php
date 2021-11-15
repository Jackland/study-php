<?php

namespace App\Enums\Message;

use Framework\Enum\BaseEnum;

class MsgCustomerExtLanguageType extends BaseEnum
{
    const NOT_LIMIT = 0;
    const CHINESE = 1;
    const ENGLISH = 2;

    /**
     * @return string[]
     */
    public static function getViewItems(): array
    {
        return [
            self::NOT_LIMIT => 'All are acceptable',
            self::CHINESE => 'Chinese Only',
            self::ENGLISH => 'English Only',
        ];
    }
}
