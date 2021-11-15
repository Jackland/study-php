<?php

namespace App\Helper;

use App\Enums\Common\CountryEnum;
use Exception;
use Framework\Helper\Html;
use ModelLocalisationCurrency;

class CurrencyHelper
{
    /**
     * 获取当前的货币符号
     * @return string
     */
    public static function getCurrentCode(): string
    {
        return session('currency', 'USD');
    }

    /**
     * 获取货币配置
     * @return array[]
     */
    public static function getCurrencyConfig()
    {
        // 对应表 oc_currency
        return [
            'GBP' =>
                [
                    'currency_id' => 1,
                    'symbol_left' => '£',
                    'symbol_right' => '',
                    'decimal_place' => '2',
                ],
            'USD' =>
                [
                    'currency_id' => 2,
                    'symbol_left' => '$',
                    'symbol_right' => '',
                    'decimal_place' => '2',
                ],
            'EUR' =>
                [
                    'currency_id' => 3,
                    'symbol_left' => '',
                    'symbol_right' => '€',
                    'decimal_place' => '2',
                ],
            'JPY' =>
                [
                    'currency_id' => 4,
                    'symbol_left' => '￥',
                    'symbol_right' => '',
                    'decimal_place' => '0',
                ],
            'UUU' =>
                [
                    'currency_id' => 5,
                    'symbol_left' => '',
                    'symbol_right' => '',
                    'decimal_place' => '2',
                ],
        ];
    }

    /**
     * 格式化价格
     * @param string|float|int $number
     * @param array $config [need_format,value,is_symbol]说明看方法内
     * @return string
     */
    public static function formatPrice($number, array $config = [])
    {
        $config = array_merge([
            'currency' => null, // 币种 code，类似 USD，默认为当前币种
            'need_format' => true, // 是否需要格式化数字，小数点后不足会补0
            'value' => '', // 值是否需要乘以一个倍数，不需要不传
            'is_symbol' => true, // 是否需要加上货币符号
            'number_is_string' => false, // number 是否为字符串，该值为 true 时，不对 number 进行格式化等操作，仅追加货币符号
            'price_options' => [], // 金额的样式
            'symbol_options' => [], // 货币符号的样式
            'japan' => 'round', // 日本国别默认用round
        ], $config);
        $currencies = static::getCurrencyConfig();

        // 币种
        if (!$config['currency']) {
            $config['currency'] = static::getCurrentCode();
        }
        $currency = $currencies[$config['currency']] ?? $currencies['USD'];

        $isNegative = false;
        if (!$config['number_is_string']) {
            bcscale(6);
            // 乘倍率
            if ($config['value']) {
                $number = (float)bcmul($number, $config['value']);
            }
            // 判断是否为负数
            $isNegative = bccomp($number, 0) === -1;
            // 转换成正数并四舍五入
            $decimalPlace = (int)$currency['decimal_place'];
            if ($config['japan'] == 'ceil' && $config['currency'] == 'JPY') {
                $number = ceil(abs($number));
            } else {
                $number = round(abs($number), $decimalPlace);
            }
            // 格式化
            if ($config['need_format']) {
                $number = number_format($number, $decimalPlace);
            }
        }
        // 货币金额格式
        if ($config['price_options']) {
            $options = $config['price_options'];
            $tag = $options['tag'] ?? 'span';
            unset($options['tag']);
            $number = Html::tag($tag, $number, $options);
        }
        // 是否要加货币符号
        if ($config['is_symbol']) {
            // 货币符号的样式
            if ($config['symbol_options']) {
                $options = $config['symbol_options'];
                $tag = $options['tag'] ?? 'span';
                unset($options['tag']);
                $currency['symbol_left'] = $currency['symbol_left'] ? Html::tag($tag, $currency['symbol_left'], $options) : '';
                $currency['symbol_right'] = $currency['symbol_right'] ? Html::tag($tag, $currency['symbol_right'], $options) : '';
            }
            // 前后货币符号
            $number = "{$currency['symbol_left']}{$number}{$currency['symbol_right']}";
        }
        // 补上负号
        return ($isNegative ? '-' : '') . $number;
    }

    /**
     * 根据currency code获取货币转化率
     * 务必保证传入的是正确的currency名称
     * GBP,USD,EUR,JPY,UUU
     * @param string $fromCurrency
     * @param string $toCurrency
     * @return float
     * @throws Exception
     */
    public static function getExchangeRate(string $fromCurrency, string $toCurrency): float
    {
        /** @var ModelLocalisationCurrency $currencyModel */
        $currencyModel = load()->model('localisation/currency');
        $exchangeRate = $currencyModel->getExchangeRate($fromCurrency, $toCurrency);
        return (float)$exchangeRate;
    }

    /**
     * 根据国别id获取货币转化率
     * 目前只支持4个国别的货币转化 中国的存在问题
     * @param int $fromCountryId
     * @param int $toCountryId
     * @return float
     * @throws Exception
     * @see CountryEnum
     */
    public static function getExchangeRateByCountryId(int $fromCountryId, int $toCountryId): float
    {
        return static::getExchangeRate(
            CountryHelper::getCurrencyUnitNameById($fromCountryId),
            CountryHelper::getCurrencyUnitNameById($toCountryId)
        );
    }

    //单纯的获取货币符号 $等
    public static function getCurrentSymbol()
    {
        $configs = self::getCurrencyConfig();

        return ($configs[self::getCurrentCode()]['symbol_left'] ?? '') . ($configs[self::getCurrentCode()]['symbol_right'] ?? '');
    }
}
