<?php

namespace App\Enums\Message;

use Framework\Enum\BaseEnum;

class MsgReceiveType extends BaseEnum
{
    const USER = 1;
    const PLATFORM_SECRETARY = 2;
    const SYSTEM = 3;
}
