<?php

namespace App\Enums\Future;

use App\Models\Futures\FuturesMarginAgreement;
use Framework\Enum\BaseEnum;

/**
 * Class FuturesMarginAgreementStatus
 * @package App\Enums\Future
 * @see FuturesMarginAgreement status
 */
class FuturesMarginAgreementStatus extends BaseEnum
{
    const APPLIED = 1;
    const PENDING = 2;
    const APPROVED = 3;
    const REJECTED = 4;
    const CANCELED = 5;
    const TIMEOUT = 6;
    const SOLD = 7;
}
