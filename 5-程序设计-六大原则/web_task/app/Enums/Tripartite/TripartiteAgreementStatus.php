<?php

namespace App\Enums\Tripartite;

use App\Enums\BaseEnum;

class TripartiteAgreementStatus extends BaseEnum
{
    const TO_BE_SIGNED = 1; // 待seller处理
    const CANCELED = 5; // 已取消
    const REJECTED  = 10; // seller拒绝
    const TO_BE_ACTIVE  = 15; // 待生效
    const ACTIVE  = 20; // 协议中
    const TERMINATED  = 25; // 已终止

    /**
     * 审核通过的状态
     * @return int[]
     */
    public static function approvedStatus(): array
    {
        return [static::TO_BE_ACTIVE, static::ACTIVE];
    }
}
