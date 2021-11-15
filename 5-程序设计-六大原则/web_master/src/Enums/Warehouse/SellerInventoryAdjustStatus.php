<?php

namespace App\Enums\Warehouse;


use Framework\Enum\BaseEnum;

class SellerInventoryAdjustStatus extends BaseEnum
{
    const TO_AUDIT = 1; // 待审核
    const AUDITED = 2; // 审核通过
    const AUDIT_FAILED = 3; // 审核不通过
    const CANCEL = 4; // 取消
    // const TO_CONFIRM = 5; // 待确认 - 33395 Seller盘亏规则修改
    const TO_RECHARGE = 6; // 结算中
    const RECHARGED = 7; // 已结算

    public static function getViewItems()
    {
        return [
            self:: TO_AUDIT => __('待审核', [], 'enums/warehouse'),
//            self:: AUDITED => __('审核通过', [], 'enums/warehouse'),
            self:: AUDIT_FAILED => __('审核不通过', [], 'enums/warehouse'),
//            self::CANCEL => __('取消', [], 'enums/warehouse'),
          //  self::TO_CONFIRM => __('待确认', [], 'enums/warehouse'),
            self::TO_RECHARGE => __('结算中', [], 'enums/warehouse'),
            self::RECHARGED => __('已结算', [], 'enums/warehouse'),
        ];
    }

}
