<?php

namespace App\Enums\Product\Channel;

use Framework\Enum\BaseEnum;

class CacheTimeToLive extends BaseEnum
{
    const ONE_MINUTE = 60;
    const FIVE_MINUTES = 300;
    const FIFTEEN_MINUTES = 900;
    const THIRTY_MINUTES = 1800;
    const ONE_HOUR = 3600;
    const THREE_HOURS = 10800;
}
