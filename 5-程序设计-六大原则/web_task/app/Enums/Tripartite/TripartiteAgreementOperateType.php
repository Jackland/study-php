<?php

namespace App\Enums\Tripartite;

use App\Enums\BaseEnum;

class TripartiteAgreementOperateType extends BaseEnum
{
    const CREATE_AGREEMENT = 1; // 创建协议
    const AGREE_AGREEMENT_SIGNED = 2; // 同意协议签署
    const REJECT_AGREEMENT_SIGNED = 3; // 拒绝协议签署
    const SEND_TERMINATED_REQUEST = 4; // 发起终止请求
    const AGREE_TERMINATED_REQUEST = 5; // 同意终止
    const REJECT_TERMINATED_REQUEST = 6; // 拒绝终止
    const CANCEL_AGREEMENT = 7; // 取消协议
    const AUTO_TERMINATED = 8; // 自动终止
    const CANCEL_REQUEST_AUTO_CANCEL = 9; // 取消申请自动取消
    const TERMINATE_REQUEST_AUTO_CANCEL = 10; // 终止申请自动取消
    const SEND_CANCEL_REQUEST = 11; // 发起取消申请
    const AGREE_CANCEL_REQUEST = 12; // 同意取消
    const REJECT_CANCEL_REQUEST = 13; // 拒绝取消
}
