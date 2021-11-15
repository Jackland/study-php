<?php

namespace App\Enums\Margin;

use Framework\Enum\BaseEnum;

/**
 * 现货状态
 *
 * Class MarginAgreementStatus
 * @package App\Enums\Margin
 */
class MarginAgreementStatus extends BaseEnum
{
    const IGNORE = -1; //扩展的状态，并非tb_sys_margin_agreement_status里面的状态

    const APPLIED = 1;//提交保证金申请的状态
    const PENDING = 2;//Seller点击了保证金协议申请进行查看
    const APPROVED = 3;//Seller同意保证金申请
    const REJECTED = 4;//Seller拒绝保证金申请
    const TIME_OUT = 5;//后台管理系统设置了保证金模板的申请有效期（天），超过有效期，协议Seller未处理，则此条协议过期
    const SOLD = 6;//Buyer完成购买保证金链接后，协议状态
    const CANCELED = 7;//applied状态下,buyer取消保证金协议
    const COMPLETED = 8;//协议完成
    const BACK_ORDER = 9;//现货保证金协议Seller未成功履约
    const TERMINATED = 10;//现货保证金协议Buyer未成功履约
    const FAILED = 11;//Seller与Buyer协商后终止

    public static function getViewItems()
    {
        return [
            static::APPLIED => 'Applied',
            static::PENDING => 'Pending',
            static::APPROVED => 'Approved',
            static::REJECTED => 'Rejected',
            static::TIME_OUT => 'Timeout',
            static::SOLD => 'To be paid',
            static::CANCELED => 'Canceled',
            static::COMPLETED => 'Completed',
            static::BACK_ORDER => 'Back Order',
            static::TERMINATED => 'Default',
            static::FAILED => 'Terminated',
            static::IGNORE => 'Ignore',

        ];
    }

    public static function getColor($status)
    {
        $colors = [
            static::APPLIED => '#004BD8',
            static::PENDING => '#333333',
            static::APPROVED => '#004BD8',
            static::REJECTED => '#333333',
            static::TIME_OUT => '#999999',
            static::SOLD => '#FF6600',
            static::CANCELED => '#999999',
            static::COMPLETED => '#333333',
            static::BACK_ORDER => '#999999',
            static::TERMINATED => '#999999',
            static::FAILED => '#999999',
            static::IGNORE => '#999999',
        ];
        return $colors[$status];
    }

    //1 2 3 5状态用到较多，拎出来
    public static function getFrontNeedStatus()
    {
        return [static::APPLIED, static::PENDING, static::APPROVED, static::TIME_OUT];
    }

    /**
     * 同意之前的状态
     * @return int[]
     */
    public static function beforeApprovedStatus() :array
    {
        return [static::APPLIED, static::PENDING, static::APPROVED];
    }

    /**
     * 待支付保证金
     * @return int[]
     */
    public static function marginDepositToBePaidStatus() :array
    {
        return [self::APPROVED];
    }

    /**
     * 待处理
     * @return int[]
     */
    public static function toBeProcessedStatus() :array
    {
        return [self::APPLIED, self::PENDING];
    }

    /**
     * 待支付尾款
     * @return int[]
     */
    public static function duePaymentToPaidStatus() :array
    {
        return [self::SOLD];
    }

    /**
     * 支付头款后
     * @return int[]
     */
    public static function afterSoldStatus() :array
    {
        return [static::SOLD, static::COMPLETED, static::BACK_ORDER, static::TERMINATED];
    }

}
