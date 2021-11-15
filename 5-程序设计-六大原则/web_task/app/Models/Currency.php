<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;

/**
 * Class Currency
 * @package App\Models
 */
class Currency
{

    /**
     * @var array $currencies
     */
    private static $currencies = [];


    private static function getCurrencies()
    {
        $objs = \DB::connection('mysql_proxy')
            ->table('oc_currency as curr')
            ->join('oc_country as cou', 'cou.currency_id', '=', 'curr.currency_id')
            ->select([
                'cou.country_id',
                'curr.currency_id',
                'curr.code', 'curr.title', 'curr.symbol_left', 'curr.symbol_right', 'curr.decimal_place', 'curr.value'
            ])
            ->get();
        foreach ($objs as $obj) {
            static::$currencies[$obj->country_id] = [
                'country_id' => $obj->country_id,
                'currency_id' => $obj->currency_id,
                'title' => $obj->title,
                'symbol_left' => $obj->symbol_left,
                'symbol_right' => $obj->symbol_right,
                'decimal_place' => $obj->decimal_place,
                'value' => $obj->value,
            ];
        }
    }

    /**
     * 格式化货币金额
     *
     * @param float|string $number 待格式化的金额
     * @param int|string $country_id 国家ID
     * @param bool $thousands_format
     * @return string
     */
    public static function format($number, $country_id, $thousands_format = true)
    {
        if (empty(static::$currencies)) {
            static::getCurrencies();
        }

        // 如果没有对应国家的货币，直接返回原金额
        if (!isset(static::$currencies[$country_id])) {
            return $number;
        }

        /**
         * 保留指定位小数
         */
        $money = round((float)$number, (int)static::$currencies[$country_id]['decimal_place']);

        // 如果不进行千位格式化 直接返回
        if (!$thousands_format) {
            return $money;
        }

        // 是否为负数
        $is_negative = false;
        if ($money < 0) {
            $money = -$money;
            $is_negative = true;
        }

        $money_string = '';
        if (static::$currencies[$country_id]['symbol_left']) {
            $money_string .= static::$currencies[$country_id]['symbol_left'];
        }

        $money_string .= number_format($money, (int)static::$currencies[$country_id]['decimal_place']);

        if (static::$currencies[$country_id]['symbol_right']) {
            $money_string .= static::$currencies[$country_id]['symbol_right'];
        }

        return ($is_negative ? '-' : '') . $money_string;
    }

}
