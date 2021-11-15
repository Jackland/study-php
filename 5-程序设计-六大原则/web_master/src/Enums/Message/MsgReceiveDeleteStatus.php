<?php

namespace App\Enums\Message;

use Framework\Enum\BaseEnum;

class MsgReceiveDeleteStatus extends BaseEnum
{
    const NOT_DELETED = 0;
    const TRASH = 1;
    const DELETED = 2;
}
