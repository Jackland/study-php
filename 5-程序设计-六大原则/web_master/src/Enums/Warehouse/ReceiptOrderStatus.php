<?php

namespace App\Enums\Warehouse;

use Framework\Enum\BaseEnum;

class ReceiptOrderStatus extends BaseEnum
{
    const ACCOUNT_MANAGER_REVIEWING = 15; // 运营顾问确认中  seller申请入库单的时候还没有任何一个入库单同步到海运系统，则入库单提交成功之后状态为【运营顾问确认中】
    const ABANDONED = 0; // 已废弃
    const TO_SUBMIT = 1; // 待提交申请
    const APPLIED = 2; // 已申请
    const DIVIDED = 3; // 已分仓
    const BOOKED = 5; // 已订舱
    const TO_BE_RECEIVED = 6; // 待收货
    const RECEIVED = 7; // 已收货
    const CANCEL = 9; // 已取消
    const EDIT_PENDING = 10; // 修改待确认
    const CANCEL_PENDING = 11; // 取消待确认

    public static function getViewItems()
    {
        return [
            self::ACCOUNT_MANAGER_REVIEWING => __('运营顾问确认中', [], 'enums/warehouse'),
            self::ABANDONED => __('已取消', [], 'enums/warehouse'),
            self::TO_SUBMIT => __('待提交申请', [], 'enums/warehouse'),
            self::APPLIED => __('已申请', [], 'enums/warehouse'),
            self::DIVIDED => __('已分仓', [], 'enums/warehouse'),
            self::BOOKED => __('已订舱', [], 'enums/warehouse'),
            self::TO_BE_RECEIVED => __('待收货', [], 'enums/warehouse'),
            self::RECEIVED => __('已收货', [], 'enums/warehouse'),
            self::CANCEL => __('已取消', [], 'enums/warehouse'),
            self::EDIT_PENDING => __('修改待确认', [], 'enums/warehouse'),
            self::CANCEL_PENDING => __('取消待确认', [], 'enums/warehouse'),
        ];
    }

    public static function reDivisionStatus()
    {
        return [
            self::DIVIDED,
            self::BOOKED,
        ];
    }

    public static function disableEditStatus()
    {
        return [
            self::RECEIVED,
            self::CANCEL,
            self::EDIT_PENDING,
            self::CANCEL_PENDING,
            self::ABANDONED
        ];
    }

    public static function canAddStatus()
    {
        return [
            self::TO_SUBMIT,
            self::APPLIED,
        ];
    }

    public static function disableCancelStatus()
    {
        return [
            self::TO_BE_RECEIVED,
            self::RECEIVED,
            self::CANCEL,
            self::EDIT_PENDING,
            self::CANCEL_PENDING,
            self::ABANDONED
        ];
    }

    //取消操作时 显示 需要其他部门待确认的提示框
    public static function doCancelShowConfirmStatus()
    {
        return [
            self::DIVIDED,
            self::BOOKED,
        ];
    }

    //需要海运取消
    public static function needSyncOceanCancelStatus()
    {
        return [
            self::APPLIED,
            self::DIVIDED,
            self::BOOKED,
        ];
    }

    /**
     * 已同步海运后的状态
     * @return int[]
     */
    public static function synchronizedOceanShippingStatus(): array
    {
        return [
            self::APPLIED,
            self::DIVIDED,
            self::BOOKED,
            self::TO_BE_RECEIVED,
            self::RECEIVED,
            self::CANCEL,
            self::EDIT_PENDING,
            self::CANCEL_PENDING,
        ];
    }

    /**
     * @return int[]
     */
    public static function appliedStatus(): array
    {
        return [
            self::ACCOUNT_MANAGER_REVIEWING,
            self::APPLIED,
        ];
    }
}
