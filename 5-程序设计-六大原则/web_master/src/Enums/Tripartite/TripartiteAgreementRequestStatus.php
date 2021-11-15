<?php

namespace App\Enums\Tripartite;

use Framework\Enum\BaseEnum;

class TripartiteAgreementRequestStatus extends BaseEnum
{
    const PENDING = 1; // 待审核
    const APPROVED = 2; // 同意
    const REJECTED  = 3; // 拒绝
    const CANCEL  = 4; // 取消
    const EXPIRED  = 5; // 过期

    const TERMINATION_TYPE = 1;//类型终止
    const CANCEL_TYPE = 2;//类型取消

}
