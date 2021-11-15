<?php

namespace App\Enums\Buyer;

use Framework\Enum\BaseEnum;

class BuyerVATType extends BaseEnum
{
    const DEFAULT = 0; // 默认
    const GERMANY_LOCALIZATION = 1; // 德国本地
    const EUROPEAN_UNION = 2; // 欧盟
}
