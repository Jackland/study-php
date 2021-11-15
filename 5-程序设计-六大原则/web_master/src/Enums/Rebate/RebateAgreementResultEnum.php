<?php

namespace App\Enums\Rebate;

use Framework\Enum\BaseEnum;

class RebateAgreementResultEnum extends BaseEnum
{
    const HISTORICAL_DATA = -1; // 默认未approved前均为0
    const __DEFAULT = 0; // 默认未approved前均为0
    const ACTIVE = 1; // rebate result active
    const DUE_SOON = 2; // 协议到期前7天
    const TERMINATED = 3; // Buyer 在 seller 同意 协议后终止
    const FAILED = 4; // 协议到期完成失败
    const FULFILLED = 5; // 协议到期完成
    const Processing = 6; // request 申请中
    const REBATE_PAID = 7; // 协议到期 request完成
    const REBATE_DECLINED = 8; // 协议到期 request拒绝
}
