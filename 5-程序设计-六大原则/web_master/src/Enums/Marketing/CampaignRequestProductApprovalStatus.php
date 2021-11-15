<?php

namespace App\Enums\Marketing;

use Framework\Enum\BaseEnum;

class CampaignRequestProductApprovalStatus extends BaseEnum
{
    const PENDING = 1;
    const APPROVED = 2;
    const REFUSED = 3;
    const CANCELED = 4;
}
