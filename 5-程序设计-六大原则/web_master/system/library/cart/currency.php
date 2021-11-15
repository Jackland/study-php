<?php

namespace Cart;
class Currency
{
    private $currencies = array();

    /**
     * @var \DB $db
     */
    private $db;

    /**
     * Currency constructor.
     * @param \Registry $registry
     */
    public function __construct($registry)
    {
        $this->db = $registry->get('db');
        $this->language = $registry->get('language');

        $query = $this->db->query("SELECT currency_id,code,title,symbol_left,symbol_right,decimal_place,value FROM " . DB_PREFIX . "currency");

        foreach ($query->rows as $result) {
            $this->currencies[$result['code']] = array(
                'currency_id' => $result['currency_id'],
                'title' => $result['title'],
                'symbol_left' => $result['symbol_left'],
                'symbol_right' => $result['symbol_right'],
                'decimal_place' => $result['decimal_place'],
                'value' => $result['value']
            );
        }
    }

    public function format($number, $currency, $value = '', $format = true)
    {
        $symbol_left = $this->currencies[$currency]['symbol_left'];
        $symbol_right = $this->currencies[$currency]['symbol_right'];
        $decimal_place = $this->currencies[$currency]['decimal_place'];

        if (!$value) {
            /*$value = $this->currencies[$currency]['value'];*/
            $value = 1.00;
        }

        $amount = $value ? (float)$number * $value : (float)$number;

        $amount = round($amount, (int)$decimal_place);

        if (!$format) {
            return $amount;
        }

        $string = '';

        if ($symbol_left) {
            $string .= $symbol_left;
        }

        $string .= number_format($amount, (int)$decimal_place, $this->language->get('decimal_point'), $this->language->get('thousand_point'));

        if ($symbol_right) {
            $string .= $symbol_right;
        }

        return $string;
    }

    /**
     * @param float|string $number
     * @param string $currency
     * @param string $value
     * @param bool $format
     * @param null|int $decimal_place
     * @return float|int|string
     * @version 2019-10-14 10:14:50 lester.you 如果为负数 负号 返回值最前面
     */
    public function formatCurrencyPrice($number, $currency, $value = '', $format = true, $decimal_place = null)
    {
        $symbol_left = $this->currencies[$currency]['symbol_left'];
        $symbol_right = $this->currencies[$currency]['symbol_right'];
        $decimal_place = is_null($decimal_place) ? $this->currencies[$currency]['decimal_place'] : $decimal_place;

        if (!$value) {
            $value = 1.00;
        }

        $amount = $value ? (float)$number * $value : (float)$number;

        $amount = round($amount, (int)$decimal_place);

        if (!$format) {
            return $amount;
        }
        // 是否为负数
        $is_negative = false;
        if ($amount < 0) {
            $amount = -$amount;
            $is_negative = true;
        }

        $string = '';

        if ($symbol_left) {
            $string .= $symbol_left;
        }

        $string .= number_format($amount, (int)$decimal_place, $this->language->get('decimal_point'), $this->language->get('thousand_point'));

        if ($symbol_right) {
            $string .= $symbol_right;
        }

        return ($is_negative ? '-' : '') . $string;
    }

    /**
     * 汇率转换
     *
     * @param float $value
     * @param string $code_from
     * @param string $code_to
     * @return mixed
     */
    public function convert($value, $code_from, $code_to)
    {
        /**
         * 汇率
         * @var float $exchange_rate_from
         * @var float $exchange_rate_to
         */
        if (isset($this->currencies[$code_from])) {
            $exchange_rate_from = $this->currencies[$code_from]['value'];
        } else {
            $exchange_rate_from = 1;
        }

        /**
         * @var int $decimal_place 小数位
         */
        if (isset($this->currencies[$code_to])) {
            $exchange_rate_to = $this->currencies[$code_to]['value'];
            $decimal_place = $this->currencies[$code_to]['decimal_place'];
        } else {
            $exchange_rate_to = 1;
            $decimal_place = 2;
        }

        return bcmul($value, $exchange_rate_to / $exchange_rate_from, $decimal_place);
    }

    public function getId($currency)
    {
        if (isset($this->currencies[$currency])) {
            return $this->currencies[$currency]['currency_id'];
        } else {
            return 0;
        }
    }

    public function getSymbolLeft($currency)
    {
        if (isset($this->currencies[$currency])) {
            return $this->currencies[$currency]['symbol_left'];
        } else {
            return '';
        }
    }

    public function getSymbolRight($currency)
    {
        if (isset($this->currencies[$currency])) {
            return $this->currencies[$currency]['symbol_right'];
        } else {
            return '';
        }
    }

    public function getDecimalPlace($currency)
    {
        if (isset($this->currencies[$currency])) {
            return $this->currencies[$currency]['decimal_place'];
        } else {
            return 0;
        }
    }

    public function getValue($currency)
    {
        if (isset($this->currencies[$currency])) {
            return $this->currencies[$currency]['value'];
        } else {
            return 0;
        }
    }

    public function has($currency)
    {
        return isset($this->currencies[$currency]);
    }
}
