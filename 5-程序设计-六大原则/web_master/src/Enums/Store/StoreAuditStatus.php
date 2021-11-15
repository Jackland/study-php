<?php

namespace App\Enums\Store;

use Framework\Enum\BaseEnum;

class StoreAuditStatus extends BaseEnum
{
    //审核状态
    const PENDING = 1;//审核中
    const APPROVED = 2;//审核通过
    const NOT_APPROVED = 3;//审核不通过

    //返回[1,3]
    public static function getChangeStatus()
    {
        return [self::PENDING, self::NOT_APPROVED];
    }

    public static function getViewItems()
    {
        return [
            self::PENDING => 'Request Pending',
            self::APPROVED => 'Request Approved',
            self::NOT_APPROVED => 'Request Declined',
        ];
    }

    public static function getClassItems($auditStatus)
    {
        $items = [
            self::PENDING => 'pending',
            self::APPROVED => 'approved',
            self::NOT_APPROVED => 'not-approved',
        ];;
        return isset($items[$auditStatus]) ? $items[$auditStatus] : '';
    }

}
