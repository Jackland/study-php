<?php

namespace App\Helper;

class MoneyHelper
{
    /**
     * 金额均分，向下舍入
     * @param float $amount
     * @param int $quantity
     * @param int $precision
     * @return float|int
     */
    public static function averageAmountFloor(float $amount, int $quantity, int $precision)
    {
        return floor($amount / $quantity * pow(10, $precision)) / pow(10, $precision);
    }

    /**
     * 金额进位
     * @param float $amount
     * @param int $precision
     * @return string|null
     */
    public static function upperAmount(float $amount, int $precision = 2)
    {
        if ($precision == 0) {
            if ((int)$amount == $amount) {
                return (int)$amount;
            }
        }

        $pow = pow(10, $precision);
        $ceilAmount = ceil(bcmul($amount, $pow, 10));

        return bcdiv($ceilAmount, $pow, $precision);
    }

    /**
     * 格式化价格
     * @param $price
     * @return string
     */
    public static function formatPrice($price)
    {
        return customer()->isJapan()
            ? number_format($price, 0, '.', '')
            : number_format($price, 2, '.', '');
    }
}
