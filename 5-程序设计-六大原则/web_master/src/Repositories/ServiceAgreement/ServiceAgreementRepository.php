<?php

namespace App\Repositories\ServiceAgreement;

use App\Enums\Common\YesNoEnum;
use App\Models\ServiceAgreement\AgreementVersion;
use App\Models\ServiceAgreement\AgreementVersionSign;
use Carbon\Carbon;

class ServiceAgreementRepository
{
    /**
     * 获取客户签署的最近一条协议版本
     * @param int $agreementId
     * @param int $customerId
     * @return AgreementVersionSign
     */
    public function getCustomerFinallyAgreementVersionSign(int $agreementId, int $customerId)
    {
        return AgreementVersionSign::query()
            ->where('agreement_id', $agreementId)
            ->where('customer_id', $customerId)
            ->orderByDesc('version_id')
            ->first();
    }

    /**
     * 获取某个协议最新一条协议版本
     * @param int $agreementId
     * @param int $versionId
     * @return AgreementVersion
     */
    public function getLastAgreementVersion(int $agreementId, int $versionId)
    {
        return AgreementVersion::query()
            ->where('agreement_id', $agreementId)
            ->where('status', YesNoEnum::YES)
            ->when(!empty($versionId), function ($q) use ($versionId) {
                $q->where('id' , '>', $versionId);
            })
            ->where('is_deleted', YesNoEnum::NO)
            ->where('effect_time', '<=', Carbon::now()->toDateTimeString())
            ->orderByDesc('id')
            ->first();
    }

    /**
     * 检查某个用户是否需要签署协议
     * @param int $agreementId
     * @param int $customerId
     * @return AgreementVersion|false
     */
    public function checkCustomerSignAgreement(int $agreementId, int $customerId)
    {
        $sign = $this->getCustomerFinallyAgreementVersionSign(...func_get_args());
        $versionId = empty($sign) ? 0 : $sign->version_id;

        $lastVersion = $this->getLastAgreementVersion($agreementId, $versionId);
        if (empty($lastVersion)) {
            return false;
        }

        // 未签署过的客户，直接需要签署最新一条
        if ($versionId == 0) {
            return $lastVersion;
        }

        // 已签署过的根据是否需要签署来判断
        if (!$lastVersion->is_sign) {
            return false;
        }

        return $lastVersion;
    }
}
