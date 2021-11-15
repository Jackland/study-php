<?php

namespace App\Services\Future;

use App\Components\Storage\StorageCloud;
use App\Enums\Future\FuturesMarginApplyStatus;
use App\Enums\Future\FuturesMarginApplyType;
use App\Models\Futures\FuturesAgreementApply;
use App\Models\Futures\FuturesAgreementFile;
use App\Models\Futures\FuturesMarginMessage;
use App\Repositories\Futures\AgreementApplyepository;
use Throwable;

class AgreementApply
{
    /**
     * 添加申诉申请
     * @param $agreementId
     * @param int $customerId
     * @param $data
     * @param $files
     * @return bool
     */
    public function addSellerAppeal($agreementId, $customerId, $data, $files)
    {
        // 如果有提前交付的申请，则不能申诉
        $approved = app(AgreementApplyepository::class)->isAgreementApplyExist($agreementId);
        if ($approved) {
            return false;
        }
        try {
            dbTransaction(function () use ($agreementId, $customerId, $data, $files) {
                $apply = FuturesAgreementApply::query()
                    ->where(['agreement_id' => $agreementId, 'apply_type' => FuturesMarginApplyType::APPEAL])
                    ->first();
                $now = date('Y-m-d H:i:s');
                if (!$apply) {
                    $record = [
                        'agreement_id' => $agreementId,
                        'customer_id' => $customerId,
                        'apply_type' => FuturesMarginApplyType::APPEAL,
                    ];
                    // 新增apply
                    $applyId = FuturesAgreementApply::query()->insertGetId($record);
                } else {
                    $applyId = $apply->id;
                    $map['update_time'] = $now;
                    // 如果申诉被驳回，可以继续发起申诉
                    if ($apply->status == FuturesMarginApplyStatus::REJECT) {
                        $map['status'] = FuturesMarginApplyStatus::PENDING;
                    }
                    $applyId = FuturesAgreementApply::query()
                        ->where('agreement_id', $agreementId)
                        ->where('apply_type', FuturesMarginApplyType::APPEAL)
                        ->update($map);
                }
                $message = [
                    'agreement_id' => $agreementId,
                    'customer_id' => $customerId,
                    'apply_id' => $applyId,
                    'message' => 'Seller has submitted a Force Majuere Claim to the Marketplace.<br>Reason for submitting claim: ' . $data['message'],
                    'create_time' => $now,
                ];
                $messageId = FuturesMarginMessage::query()->insertGetId($message);
                $this->handleFutureAttach($applyId, $messageId, $agreementId, $files);
            });
            return true;
        } catch (Throwable $e) {
            return false;
        }

    }

    /**
     * 附件处理
     * @param $applyId
     * @param $messageId
     * @param $agreementId
     * @param $files
     */
    function handleFutureAttach($applyId, $messageId, $agreementId, $files)
    {
        if (!empty($files)) {
            $uploaded = [];
            foreach ($files as $key => $file) {
                $originalName = $file->getClientOriginalName();
                $uploaded[$key]['file_name'] = $originalName;
                $uploaded[$key]['message_id'] = $messageId;
                $uploaded[$key]['apply_id'] = $applyId;
                $uploaded[$key]['size'] = $file->getSize();
                $path = StorageCloud::futureAppealFile()->writeFile($file, $agreementId);
                $uploaded[$key]['file_path'] = $path;
            }
            FuturesAgreementFile::query()->insert($uploaded);
        }
    }
}
