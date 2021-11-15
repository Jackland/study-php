<?php

namespace App\Repositories\Futures;

use App\Enums\Future\FuturesMarginApplyStatus;
use App\Enums\Future\FuturesMarginApplyType;
use App\Models\Futures\FuturesAgreementApply;

class AgreementApplyepository
{
    /**
     * 是否存在未处理的申请
     * @param $agreementId
     * @return bool
     */
    function isAgreementApplyExist($agreementId): bool
    {
        return FuturesAgreementApply::query()
            ->where([
                'status' => FuturesMarginApplyStatus::PENDING,
                'agreement_id' => $agreementId,
            ])
            ->where('apply_type', '!=', FuturesMarginApplyType::APPEAL)
            ->exists();
    }
}
