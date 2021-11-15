<?php

namespace App\Enums\Tripartite;

use Framework\Enum\BaseEnum;

class TripartiteAgreementStatus extends BaseEnum
{
    const TO_BE_SIGNED = 1; // 待seller处理
    const CANCELED  = 5; // 已取消
    const REJECTED = 10; // seller拒绝
    const TO_BE_ACTIVE  = 15; // 待生效
    const ACTIVE  = 20; // 协议中
    const TERMINATED  = 25; // 已终止

    /**
     * @return string[]
     */
    public static function getViewItems(): array
    {
        return [
            static::TO_BE_SIGNED => 'Process Pending',
            static::REJECTED => 'Refused',
            static::TO_BE_ACTIVE => 'To Be Effective',
            static::ACTIVE => 'Active',
            static::TERMINATED => 'Terminated',
            static::CANCELED => 'Canceled',
        ];
    }

    /**
     * 获取状态颜色
     * @return string[]
     */
    public static function getColorItems(): array
    {
        return [
            static::TO_BE_SIGNED => 'oris-bg-default',
            static::REJECTED => 'oris-bg-error',
            static::CANCELED => 'oris-bg-info',
            static::TO_BE_ACTIVE => 'oris-bg-warning',
            static::ACTIVE => 'oris-bg-success',
            static::TERMINATED => 'oris-bg-info',
        ];
    }

    /**
     * 审核通过的状态
     * @return int[]
     */
    public static function approvedStatus(): array
    {
        return [static::TO_BE_ACTIVE, static::ACTIVE];
    }

    /**
     * @return int[]
     */
    public static function pendingAndRejectedStatus(): array
    {
        return [static::TO_BE_SIGNED, static::REJECTED];
    }

    /**
     * @return int[]
     */
    public static function unapprovedStatus(): array
    {
        return [static::TO_BE_SIGNED, static::REJECTED, static::CANCELED];
    }

    /**
     * seller列表排序
     * @return int[]
     */
    public static function sellerOrderStatus(): array
    {
        return [
            static::TO_BE_SIGNED,
            static::TO_BE_ACTIVE,
            static::ACTIVE,
            static::TERMINATED,
            static::REJECTED,
            static::CANCELED,
        ];
    }

    /**
     * seller列表筛选排序
     * @return int[]
     */
    public static function sellerOrderViewItems(): array
    {
        $arr = [];
        foreach (static::sellerOrderStatus() as $v) {
            $arr[$v] = static::getViewItems()[$v];
        }

        return $arr;
    }

    /**
     * buyer列表排序
     * @return int[]
     */
    public static function buyerOrderStatus(): array
    {
        return [
            static::REJECTED,
            static::TO_BE_SIGNED,
            static::TO_BE_ACTIVE,
            static::ACTIVE,
            static::TERMINATED,
            static::CANCELED,
        ];
    }

    /**
     * buyer列表筛选排序
     * @return int[]
     */
    public static function buyerOrderViewItems(): array
    {
        $arr = [];
        foreach (static::buyerOrderStatus() as $v) {
            $arr[$v] = static::getViewItems()[$v];
        }

        return $arr;
    }
}
