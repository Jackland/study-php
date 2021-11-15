<?php

namespace App\Services\TripartiteAgreement;

use App\Components\Storage\StorageCloud;
use App\Enums\Common\YesNoEnum;
use App\Enums\Country\Country;
use App\Enums\Tripartite\TripartiteAgreementOperateType as OperateType;
use App\Enums\Tripartite\TripartiteAgreementStatus;
use App\Helper\CountryHelper;
use App\Models\Tripartite\TripartiteAgreement;
use App\Models\Tripartite\TripartiteAgreementOperate;
use App\Repositories\Seller\SellerRepository;
use App\Repositories\Tripartite\AgreementRepository;
use Carbon\Carbon;
use Framework\Exception\Exception;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * 采销（三方）协议
 *
 * Class AgreementService
 * @package App\Services\TripartiteAgreement
 */
class AgreementService
{
    const AGREEMENT_PATH = 'storage/tripartite_agreement';

    /**
     * 同意签署协议
     * @param TripartiteAgreement $agreement
     * @param int $sellerId
     * @param string $message
     * @param int $countryId
     * @throws Throwable
     */
    public function approveAgreement(TripartiteAgreement $agreement, int $sellerId, string $message = '', int $countryId = Country::AMERICAN)
    {
        [$company, $address, $name, $telephone] = app(SellerRepository::class)->getSellerCompanyInfo($sellerId);
        $replacesMap = [
            TemplateService::KEYWORD_SELLER_COMPANY => $company,
            TemplateService::KEYWORD_SELLER_ADDRESS => $address,
            TemplateService::KEYWORD_SELLER_NAME => $name,
            TemplateService::KEYWORD_SELLER_TELEPHONE => $telephone,
            TemplateService::KEYWORD_SELLER_ACCOUNT_NAME => "{$agreement->seller->screenname}({$agreement->seller->customer->user_number})",
        ];
        if (!app(TemplateService::class)->checkCompanyInfo($agreement->template, $replacesMap)) {
            throw new Exception('', 401);
        }

        // 填写seller公司信息
        $replaces = app(TemplateService::class)->generateReplaceValue($replacesMap, $agreement->template_replaces);
        $agreement->template_replace_value = json_encode($replaces, JSON_UNESCAPED_UNICODE);
        $agreement->terminate_time = $agreement->expire_time;
        $agreement->seller_approved_time = Carbon::now()->toDateTimeString();
        // 判断是否是待生效或已生效（如果是已生效，开始时间需要重置）
        if ($agreement->effect_time->gt(Carbon::now())) {
            $agreement->status = TripartiteAgreementStatus::TO_BE_ACTIVE;
        } else {
            $agreement->status = TripartiteAgreementStatus::ACTIVE;
        }

        dbTransaction(function () use ($agreement, $sellerId, $message, $countryId) {
            $agreement->save();
            $this->createOperate($agreement->id, $sellerId, OperateType::AGREE_AGREEMENT_SIGNED, $message);
        });

        $url = app(TemplateService::class)->generatePdf($agreement, self::AGREEMENT_PATH);

        if (!empty($url)) {
            $agreement->download_url = $url;
            $agreement->save();
        }
    }

    /**
     * 拒绝签署协议
     * @param int $agreementId
     * @param int $sellerId
     * @param string $message
     * @throws Throwable
     */
    public function rejectAgreement(int $agreementId, int $sellerId, string $message = '')
    {
        $agreement = TripartiteAgreement::query()->findOrFail($agreementId);
        if ($agreement->status != TripartiteAgreementStatus::TO_BE_SIGNED || $sellerId != $agreement->seller_id) {
            throw new Exception('The agreement has been updated, please refresh the page and try again.', 400);
        }

        dbTransaction(function () use ($agreement, $sellerId, $message) {
            $agreement->status = TripartiteAgreementStatus::REJECTED;
            $agreement->save();

            $this->createOperate($agreement->id, $sellerId, OperateType::REJECT_AGREEMENT_SIGNED, $message);
        });
    }

    /**
     * 下载协议
     * @param int $agreementId
     * @param int $operatorId
     * @return Response
     * @throws Exception
     */
    public function downloadAgreement(int $agreementId, int $operatorId)
    {
        $agreement = TripartiteAgreement::query()->findOrFail($agreementId);
        if ($operatorId != $agreement->seller_id && $operatorId != $agreement->buyer_id) {
            throw new Exception('', 400);
        }

        if (!empty($agreement->download_url)) {
            return StorageCloud::root()->browserDownload($agreement->download_url, "{$agreement->title}.pdf");
        }

        return app(TemplateService::class)->generatePdf($agreement);
    }

    /**
     * 添加操作日志
     * @param int $agreementId
     * @param int $customerId
     * @param int $type
     * @param string $message
     * @param int $requestId
     */
    public function createOperate(int $agreementId, int $customerId, int $type, string $message = '', int $requestId = 0)
    {
        TripartiteAgreementOperate::query()->insert([
            'agreement_id' => $agreementId,
            'request_id' => $requestId,
            'customer_id' => $customerId,
            'message' => $message,
            'type' => $type,
        ]);
    }

    /**
     * description:删除协议
     * @param array $condition 提交的post
     * @param int $customerId
     * @return array
     */
    public function delete(array $condition, int $customerId)
    {
        $agreement_id = $condition['agreement_id'] ?? 0;
        if ($agreement_id) {
            $detail = app(AgreementRepository::class)->getDetail(['agreement_id' => $condition['agreement_id']], $customerId);
            if ($detail && $detail['can_tripartite_delete'] === true) {
                $save = TripartiteAgreement::query()->find($condition['agreement_id']);
                $save->is_deleted = YesNoEnum::YES;
                $save->save();
                return [
                    'code' => 200,
                    'msg' => 'Successfully.'
                ];
            }
        }
        return [
            'code' => 400,
            'msg' => 'The agreement has been updated, please refresh the page and try again.'
        ];
    }
}
