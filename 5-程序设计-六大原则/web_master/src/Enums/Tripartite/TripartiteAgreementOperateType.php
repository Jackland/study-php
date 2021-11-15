<?php

namespace App\Enums\Tripartite;

use Framework\Enum\BaseEnum;

class TripartiteAgreementOperateType extends BaseEnum
{
    const CREATE_AGREEMENT = 1; // 创建协议
    const AGREE_AGREEMENT_SIGNED = 2; // 同意协议签署
    const REJECT_AGREEMENT_SIGNED = 3; // 拒绝协议签署
    const SEND_TERMINATED_REQUEST = 4; // 发起终止请求
    const AGREE_TERMINATED_REQUEST = 5; // 同意提前终止
    const REJECT_TERMINATED_REQUEST = 6; // 拒绝提前终止
    const CANCEL_AGREEMENT = 7; // 取消协议
    const AUTO_TERMINATED = 8; // 自动终止
    const CANCEL_REQUEST_AUTO_CANCEL = 9; // 取消申请自动取消
    const TERMINATE_REQUEST_AUTO_CANCEL = 10; // 终止申请自动取消
    const SEND_CANCEL_REQUEST = 11; // 发起取消申请
    const AGREE_CANCEL_REQUEST = 12; // 同意取消
    const REJECT_CANCEL_REQUEST = 13; // 拒绝取消
    const EDIT_AGREEMENT = 14; // 编辑协议

    /**
     *
     * @return string[]
     */
    public static function getViewItems(): array
    {
        return [
            static::CREATE_AGREEMENT => 'Initiate a Sales and Purchase Agreement',
            static::AGREE_AGREEMENT_SIGNED => 'Agree to sign the agreement',
            static::REJECT_AGREEMENT_SIGNED => 'Refuse to sign the agreement',
            static::SEND_TERMINATED_REQUEST => 'Submit the early termination request (Termination time: value)',
            static::AGREE_TERMINATED_REQUEST => 'Agree the early termination request',
            static::REJECT_TERMINATED_REQUEST => 'Refuse the early termination request',
            static::CANCEL_AGREEMENT => 'Cancel the agreement',
            static::AUTO_TERMINATED => 'Terminated',
            static::CANCEL_REQUEST_AUTO_CANCEL => 'Withdraw the cancellation request for the agreement',
            static::TERMINATE_REQUEST_AUTO_CANCEL => 'Withdraw the early termination request for the agreement',
            static::SEND_CANCEL_REQUEST => 'Request to cancel the agreement, and waiting for value to process',
            static::AGREE_CANCEL_REQUEST => 'Agree the cancellation request',
            static::REJECT_CANCEL_REQUEST => 'Refuse the cancellation request',
            static::EDIT_AGREEMENT => 'Edit the agreement'
        ];
    }

}
