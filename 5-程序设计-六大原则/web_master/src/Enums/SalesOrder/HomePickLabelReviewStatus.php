<?php

namespace App\Enums\SalesOrder;

use Framework\Enum\BaseEnum;

class HomePickLabelReviewStatus extends BaseEnum
{
    const APPLIED  = 0;
    const APPROVED = 1;
    const PENDING  = 2;
    const REJECTED = 3;
}
