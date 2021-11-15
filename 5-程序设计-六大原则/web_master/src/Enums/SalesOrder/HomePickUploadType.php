<?php

namespace App\Enums\SalesOrder;

use Framework\Enum\BaseEnum;

class HomePickUploadType extends BaseEnum
{
    // country id
    const BRITAIN_COUNTRY_ID = 222;
    const GERMANY_COUNTRY_ID = 81;

    // order mode
    const ORDER_MODE_NORMAL = 0;
    const ORDER_MODE_HOMEPICK = 3;
}