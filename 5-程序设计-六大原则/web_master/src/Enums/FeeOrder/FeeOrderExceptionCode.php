<?php

namespace App\Enums\FeeOrder;

use Framework\Enum\BaseEnum;

class FeeOrderExceptionCode extends BaseEnum
{
    const OTHERS = 228;
    const ALREADY_CANCELED = 229;
    const ALREADY_PAID = 230;
    const INFORMATION_CHANGED = 231;
}
