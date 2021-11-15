<?php

namespace App\Repositories\Pay;

use App\Models\Pay\PaymentReturnCode;

class PaymentReturnCodeRepository
{
    /**
     * @author xxl
     * @description
     * @date 15:42 2020/10/30
     * @param
     * @return string $description
     */
    public function getDescriptionByErrorCode($errorCode)
    {
        $description = PaymentReturnCode::query()->where('error_code', $errorCode)->first('description');
        return $description->description ?? null;
    }
}
