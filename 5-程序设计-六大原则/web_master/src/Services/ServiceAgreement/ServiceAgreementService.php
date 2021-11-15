<?php

namespace App\Services\ServiceAgreement;

use App\Enums\Common\YesNoEnum;
use App\Models\Customer\Customer;
use App\Models\ServiceAgreement\AgreementVersion;
use App\Models\ServiceAgreement\AgreementVersionSign;
use Carbon\Carbon;

class ServiceAgreementService
{
    /**
     * 同意协议
     * @param AgreementVersion $version
     * @param int $customerId
     */
    public function agreeServiceAgreementVersion(AgreementVersion $version, int $customerId)
    {
        AgreementVersionSign::query()
            ->where('agreement_id', $version->agreement_id)
            ->where('customer_id', $customerId)
            ->where('status', YesNoEnum::YES)
            ->update([
                'status' => YesNoEnum::NO,
                'expire_time' => Carbon::now(),
            ]);
        AgreementVersionSign::query()->insert([
            'sign_no' => $this->generateSignNo($version->id),
            'agreement_id' => $version->agreement_id,
            'version_id' => $version->id,
            'customer_id' => $customerId,
            'information_id' => $version->information_id,
            'ip' => request()->getUserIp(),
            'area' => '',
            'result' => YesNoEnum::YES,
            'status' => YesNoEnum::YES,
            'sign_time' => Carbon::now(),
            'effect_time' => Carbon::now(),
        ]);

        // 该字段也需做兼容处理
        Customer::query()->where('customer_id', $customerId)->where('service_agreement', 0)->update(['service_agreement' => 1]);
    }

    /**
     * 生成唯一的
     * @param int $versionId
     * @return string
     */
    private function generateSignNo(int $versionId): string
    {
        $no = sprintf("%03d", $versionId) . date('ymd') . rand(1000, 9999);

        if (AgreementVersionSign::query()->where('sign_no', $no)->exists()) {
            return $this->generateSignNo($versionId);
        }

        return $no;
    }
}
