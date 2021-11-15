<?php

namespace App\Enums\Charge;

use Framework\Enum\BaseEnum;

class ChargeType extends BaseEnum
{
    // 注意增加类型要在下面的收入与支出中添加
    const PAY_LIMIT_CHARGE = 1; //信用额度充值
    const PAY_CREDIT_CONSUMPTION = 2; //信用额度扣减(支付订单)
    const PAY_REFUND_CHARGE = 3; //退返品充值
    const PAY_REBATE_CHARGE = 4; //返点返金
    const PAY_MARGIN_REFUND = 5; //现货返金
    const PAY_LIMIT_RECHARGE = 6; //信用额度减值
    const PAY_AIRWALLEX_RECHARGE = 7; //Airwallex支付方式充值
    const PAY_WIRE_TRANSFER = 8; //电汇
    const PAY_PAYONEER = 9; //P卡
    const PAY_FUTURES_REFUND = 10; //期货退款
    const PAY_FEE_ORDER = 11; //支付仓租
    const PAY_PINGPONG = 12; //PingPong充值
    const PAY_SAFEGUARD = 13; //保障服务费支出
    const REFUND_SAFEGUARD = 14; //保障服务费退款
    const RECHARGE_SAFEGUARD = 15; //保障服务费理赔款
    const REFUND_STORAGE_FEE = 16; //仓租费退款

    public static function getViewItems()
    {
        return [
            static::PAY_LIMIT_CHARGE => 'Credit Line Recharge',
            static::PAY_CREDIT_CONSUMPTION => 'Credit Line Consumption',
            static::PAY_REFUND_CHARGE => 'RMA Recharge',
            static::PAY_REBATE_CHARGE => 'Rebate Recharge',
            static::PAY_MARGIN_REFUND => 'Margin Refund',
            static::PAY_LIMIT_RECHARGE => 'Credit Line Deduction',
            static::PAY_AIRWALLEX_RECHARGE => 'Airwallex Recharge',
            static::PAY_WIRE_TRANSFER => 'Wire transfer',
            static::PAY_PAYONEER => 'Payoneer',
            static::PAY_FUTURES_REFUND => 'Futures Refund',
            static::PAY_FEE_ORDER => 'Storage Fee Payment',
            static::PAY_PINGPONG => 'PingPong',
            static::PAY_SAFEGUARD => 'Protection Service Fee Payment',
            static::REFUND_SAFEGUARD => 'Protection Service Fee Refund',
            static::RECHARGE_SAFEGUARD => 'Added to Account Balance',
            static::REFUND_STORAGE_FEE => 'Storage Fee Refund',
        ];
    }

    /**
     * 获取所有收入类型
     *
     * @param false $implode 传true会返回1,2,3格式的字符串
     * @return int[]|string
     */
    public static function getRevenueTypes($implode = false)
    {
        $types = [
            static::PAY_LIMIT_CHARGE,
            static::PAY_REFUND_CHARGE,
            static::PAY_REBATE_CHARGE,
            static::PAY_MARGIN_REFUND,
            static::PAY_AIRWALLEX_RECHARGE,
            static::PAY_WIRE_TRANSFER,
            static::PAY_PAYONEER,
            static::PAY_FUTURES_REFUND,
            static::PAY_PINGPONG,
            static::REFUND_SAFEGUARD,
            static::RECHARGE_SAFEGUARD,
            static::REFUND_STORAGE_FEE,
        ];
        return $implode ? implode(',', $types) : $types;
    }

    /**
     * 获取所有支出类型
     *
     * @param false $implode 传true会返回1,2,3格式的字符串
     * @return int[]|string
     */
    public static function getPaymentTypes($implode = false)
    {
        $types = [
            static::PAY_CREDIT_CONSUMPTION,
            static::PAY_LIMIT_RECHARGE,
            static::PAY_FEE_ORDER,
            static::PAY_SAFEGUARD,
        ];
        return $implode ? implode(',', $types) : $types;
    }

    /**
     * admin limit recharge内显示的类型
     *
     * @param false $implode
     * @return int[]|string
     */
    public static function getAdminTypes($implode = false)
    {
        $types = [
            static::PAY_LIMIT_CHARGE,
            static::PAY_REFUND_CHARGE,
            static::PAY_REBATE_CHARGE,
            static::PAY_MARGIN_REFUND,
            static::PAY_LIMIT_RECHARGE,
            static::PAY_AIRWALLEX_RECHARGE,
            static::PAY_WIRE_TRANSFER,
            static::PAY_PAYONEER,
            static::PAY_FUTURES_REFUND,
            static::PAY_FEE_ORDER,
            static::PAY_PINGPONG,
            static::PAY_SAFEGUARD,
            static::REFUND_SAFEGUARD,
            static::RECHARGE_SAFEGUARD,
            static::REFUND_STORAGE_FEE,
        ];
        return $implode ? implode(',', $types) : $types;
    }
}
