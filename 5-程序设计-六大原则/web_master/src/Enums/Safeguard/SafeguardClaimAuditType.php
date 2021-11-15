<?php

namespace App\Enums\Safeguard;

use Framework\Enum\BaseEnum;

class SafeguardClaimAuditType extends BaseEnum
{
    const AUDIT_APPLEID = 0; //buyer申请理赔
    const AUDIT_PENDDING = 10; //审批中
    const AUDIT_BACKED = 11;//打回完善资料
    const AUDIT_REJECTED_SYS = 12;//系统判定失败
    const AUDIT_BACKED_TO_CHECK = 20; //资料已完善
    const AUDIT_APPROVED = 30; //审批通过
    const AUDIT_REJECTED = 40; //驳回

    public static function getBuyerHandleStatus()
    {
        return [self::AUDIT_APPLEID, self::AUDIT_BACKED,self::AUDIT_BACKED_TO_CHECK,self::AUDIT_REJECTED_SYS];
    }

}
