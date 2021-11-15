<?php

namespace App\Services\TripartiteAgreement;

use App\Enums\Common\CountryEnum;
use App\Enums\Country\Country;
use App\Enums\Tripartite\TripartiteAgreementOperateType as OperateType;
use App\Enums\Tripartite\TripartiteAgreementRequestStatus as RequestStatus;
use App\Enums\Tripartite\TripartiteAgreementRequestType as RequestType;
use App\Enums\Tripartite\TripartiteAgreementStatus;
use App\Helper\CountryHelper;
use App\Models\Tripartite\TripartiteAgreement;
use App\Models\Tripartite\TripartiteAgreementRequest;
use Carbon\Carbon;
use Framework\Exception\Exception;
use Throwable;

/**
 * 采销（三方）协议申请操作
 *
 * Class AgreementService
 * @package App\Services\TripartiteAgreement
 */
class AgreementRequestService
{
    /**
     * 终止申请
     * @param int $agreementId
     * @param int $operatorId
     * @param string $terminateTime
     * @param string $reason
     * @throws Throwable
     */
    public function terminateRequest(int $agreementId, int $operatorId, string $terminateTime, string $reason = '')
    {
        $agreement = TripartiteAgreement::query()->findOrFail($agreementId);
        if ($operatorId != $agreement->seller_id && $operatorId != $agreement->buyer_id) {
            throw new Exception('', 404);
        }

        // 不在待生效和已生效的不能申请终止
        if (!in_array($agreement->status, TripartiteAgreementStatus::approvedStatus())) {
            throw new Exception('The agreement cannot be terminated.', 400);
        }

        // 该协议已提前终止
        if ($agreement->terminate_time != $agreement->expire_time) {
            throw new Exception('The agreement has been terminated.', 400);
        }

        // 终止时间问题
        if ($terminateTime > $agreement->expire_time || $terminateTime < $agreement->effect_time) {
            throw new Exception('The termination time should be within the agreement validity.', 400);
        }

        // 已发出申请的直接返回提交成功(包含取消和终止)
        if ($this->existPendingRequestByIdAndSendId($agreement->id, $operatorId)) {
            return;
        }

        dbTransaction(function () use ($agreement, $operatorId, $terminateTime, $reason) {
            $requestId = TripartiteAgreementRequest::query()->insertGetId([
                'agreement_id' => $agreement->id,
                'sender_id' => $operatorId,
                'handle_id' => $agreement->buyer_id == $operatorId ? $agreement->seller_id : $agreement->buyer_id,
                'request_time' => $terminateTime,
                'type' => RequestType::TERMINATE,
                'reason' => $reason,
                'status' => RequestStatus::PENDING,
                'agreement_status' => $agreement->status,
            ]);

            app(AgreementService::class)->createOperate($agreement->id, $operatorId, OperateType::SEND_TERMINATED_REQUEST, $reason, $requestId);
        });
    }

    /**
     * 取消申请
     * @param int $agreementId
     * @param int $operatorId
     * @param string $reason
     * @param int $countryId
     * @throws Exception
     * @throws Throwable
     */
    public function cancelRequest(int $agreementId, int $operatorId, string $reason = '', int $countryId = CountryEnum::AMERICA)
    {
        $agreement = TripartiteAgreement::query()->findOrFail($agreementId);
        if ($operatorId != $agreement->seller_id && $operatorId != $agreement->buyer_id) {
            throw new Exception('', 404);
        }

        // 不在待生效不能申请取消
        if ($agreement->status != TripartiteAgreementStatus::TO_BE_ACTIVE) {
            throw new Exception('The agreement cannot be canceled.', 400);
        }

        // 已发出申请的直接返回提交成功(包含取消和终止)
        if ($this->existPendingRequestByIdAndSendId($agreement->id, $operatorId)) {
            return;
        }

        dbTransaction(function () use ($agreement, $operatorId, $reason, $countryId) {
            $requestId = TripartiteAgreementRequest::query()->insertGetId([
                'agreement_id' => $agreement->id,
                'sender_id' => $operatorId,
                'handle_id' => $agreement->buyer_id == $operatorId ? $agreement->seller_id : $agreement->buyer_id,
                'request_time' => Carbon::now(),
                'type' => RequestType::CANCEL,
                'reason' => $reason,
                'status' => RequestStatus::PENDING,
                'agreement_status' => $agreement->status,
            ]);

            app(AgreementService::class)->createOperate($agreement->id, $operatorId, OperateType::SEND_CANCEL_REQUEST, $reason, $requestId);
        });
    }


    /**
     * description:如果是在 待处理和已拒绝
     * @param int $agreementId
     * @param int $operatorId
     * @return int
     * @throws Throwable
     */
    public function generalCancel(int $agreementId, int $operatorId)
    {
        $agreement = TripartiteAgreement::query()->findOrFail($agreementId);
        if ($agreement->buyer_id != $operatorId) {
            throw new Exception('', 400);
        }
        $agreement->status = TripartiteAgreementStatus::CANCELED;
        $agreement->save();
        app(AgreementService::class)->createOperate($agreement->id, $operatorId, OperateType::CANCEL_AGREEMENT, '');
        return true;
    }


    /**
     * 同意终止
     * @param int $requestId
     * @param int $operatorId
     * @param string $message
     * @throws Throwable
     */
    public function agreeTerminate(int $requestId, int $operatorId, string $message = '')
    {
        $request = TripartiteAgreementRequest::query()->findOrFail($requestId);
        if ($request->handle_id != $operatorId || $request->status != RequestStatus::PENDING) {
            throw new Exception('The agreement cannot be terminated.', 400);
        }

        $agreement = TripartiteAgreement::query()->findOrFail($request->agreement_id);
        if (!in_array($agreement->status, TripartiteAgreementStatus::approvedStatus())) {
            throw new Exception('The agreement has been updated, please refresh the page and try again.', 400);
        }
        // 已终止的直接返回
        if ($agreement->terminate_time != $agreement->expire_time) {
            return;
        }

        dbTransaction(function () use ($request, $agreement, $operatorId, $message) {
            $request->status = RequestStatus::APPROVED;
            $request->save();

            $agreement->terminate_time = $request->request_time;
            $agreement->save();

            app(AgreementService::class)->createOperate($agreement->id, $operatorId, OperateType::AGREE_TERMINATED_REQUEST, $message, $request->id);

            $this->clearPendingRequest($agreement->id, RequestType::TERMINATE);
        });
    }

    /**
     * 同意取消
     * @param int $requestId
     * @param int $operatorId
     * @param string $message
     * @throws Throwable
     */
    public function agreeCancel(int $requestId, int $operatorId, string $message = '')
    {
        $request = TripartiteAgreementRequest::query()->findOrFail($requestId);
        if ($request->handle_id != $operatorId || $request->status != RequestStatus::PENDING) {
            throw new Exception('The agreement cannot be canceled.', 400);
        }

        $agreement = TripartiteAgreement::query()->findOrFail($request->agreement_id);
        if ($agreement->status != TripartiteAgreementStatus::TO_BE_ACTIVE) {
            throw new Exception('The agreement has been updated, please refresh the page and try again.', 400);
        }

        dbTransaction(function () use ($request, $agreement, $operatorId, $message) {
            $request->status = RequestStatus::APPROVED;
            $request->save();

            $agreement->status = TripartiteAgreementStatus::CANCELED;
            $agreement->save();

            app(AgreementService::class)->createOperate($agreement->id, $operatorId, OperateType::AGREE_CANCEL_REQUEST, $message, $request->id);

            $this->clearPendingRequest($agreement->id);
        });
    }

    /**
     * 拒绝终止
     * @param int $requestId
     * @param int $operatorId
     * @param string $message
     * @throws Throwable
     */
    public function rejectTerminate(int $requestId, int $operatorId, string $message = '')
    {
        $request = TripartiteAgreementRequest::query()->findOrFail($requestId);
        if ($request->handle_id != $operatorId || $request->status != RequestStatus::PENDING) {
            throw new Exception('The agreement has been updated, please refresh the page and try again.', 400);
        }

        $agreement = TripartiteAgreement::query()->findOrFail($request->agreement_id);
        if (!in_array($agreement->status, TripartiteAgreementStatus::approvedStatus())) {
            throw new Exception('The agreement has been updated, please refresh the page and try again.', 400);
        }

        dbTransaction(function () use ($request, $agreement, $operatorId, $message) {
            $request->status = RequestStatus::REJECTED;
            $request->save();

            app(AgreementService::class)->createOperate($agreement->id, $operatorId, OperateType::REJECT_TERMINATED_REQUEST, $message, $request->id);
        });
    }

    /**
     * 拒绝取消
     * @param int $requestId
     * @param int $operatorId
     * @param string $message
     * @throws Throwable
     */
    public function rejectCancel(int $requestId, int $operatorId, string $message = '')
    {
        $request = TripartiteAgreementRequest::query()->findOrFail($requestId);
        if ($request->handle_id != $operatorId || $request->status != RequestStatus::PENDING) {
            throw new Exception('The agreement has been updated, please refresh the page and try again.', 400);
        }

        $agreement = TripartiteAgreement::query()->findOrFail($request->agreement_id);
        if ($agreement->status != TripartiteAgreementStatus::TO_BE_ACTIVE) {
            throw new Exception('The agreement has been updated, please refresh the page and try again.', 400);
        }

        dbTransaction(function () use ($request, $agreement, $operatorId, $message) {
            $request->status = RequestStatus::REJECTED;
            $request->save();

            app(AgreementService::class)->createOperate($agreement->id, $operatorId, OperateType::REJECT_CANCEL_REQUEST, $message, $request->id);
        });
    }

    /**
     * 是否存在某个发起人发起的某个协议等待处理的请求
     * @param int $agreementId
     * @param int $sendId
     * @return bool
     */
    private function existPendingRequestByIdAndSendId(int $agreementId, int $sendId): bool
    {
        return TripartiteAgreementRequest::query()
            ->where('agreement_id', $agreementId)
            ->where('sender_id', $sendId)
            ->where('status', RequestStatus::PENDING)
            ->exists();
    }

    /**
     * 将其他等待处理的请求状态改为已过期
     * @param int $agreementId
     * @param int|null $requestType
     */
    private function clearPendingRequest(int $agreementId, ?int $requestType = null)
    {
        TripartiteAgreementRequest::query()
            ->where('agreement_id', $agreementId)
            ->where('status', RequestStatus::PENDING)
            ->when(!empty($requestType), function ($q) use ($requestType) {
                $q->where('type', $requestType);
            })
            ->update(['status' => RequestStatus::EXPIRED]);
    }
}
