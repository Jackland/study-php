<?php

namespace App\Enums\Warehouse;


use Framework\Enum\BaseEnum;

class ReceiptsOrderHistoryType extends BaseEnum
{
    const CREATE = 0; // 创建入库单
    const CREATE_AND_APPLY = 1; // 创建申请入库单
    const PENDING = 2; // 修改待审核
    const APPROVD = 3; // 审核通过
    const FAILED = 4; // 审核不通过
    const RETURN = 5; // 海运回传
    const NO_AUDITNO_AUDIT = 9; // 不需要审核

    public static function getViewItems()
    {
        return [
            self::CREATE => __('创建入库单并保存', [], 'enums/warehouse'),
            self::CREATE_AND_APPLY => __('创建入库单并提交申请', [], 'enums/warehouse'),
            self::PENDING => __('修改申请', [], 'enums/warehouse'),
            self::APPROVD => __('审核通过', [], 'enums/warehouse'),
            self::FAILED => __('审核不通过', [], 'enums/warehouse'),
            self::RETURN => __('海运回传', [], 'enums/warehouse'),
            self::NO_AUDITNO_AUDIT => __('入库单修改成功', [], 'enums/warehouse'),
        ];
    }
}
