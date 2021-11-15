<?php

namespace App\Enums\Seller;

use Framework\Enum\BaseEnum;

class SellerStoreAuditStatus extends BaseEnum
{
    const PREVIEW = 5; // 预览
    const DRAFT = 10; // 草稿
    const AUDIT_WAIT = 20; // 待审核
    const AUDIT_PASS = 30; // 审核通过
    const AUDIT_REFUSE = 40; // 审核驳回

    public static function getViewItems()
    {
        return [
            self::PREVIEW => '预览',
            self::DRAFT => '草稿',
            self::AUDIT_WAIT => '待审核',
            self::AUDIT_PASS => '审核通过',
            self::AUDIT_REFUSE => '审核驳回',
        ];
    }
}
