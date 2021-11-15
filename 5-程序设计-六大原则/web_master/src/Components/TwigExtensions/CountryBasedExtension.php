<?php

namespace App\Components\TwigExtensions;

use App\Helper\CurrencyHelper;

/**
 * 与国家相关的扩展
 */
class CountryBasedExtension extends AbsTwigExtension
{
    protected $filters = [
        'parsePrice',
        'formatZone',
        'formatPrice',
        'formatSpTime',
    ];

    public function parsePrice($price, $is_japan)
    {
        if ($is_japan) {
            return sprintf('%d', round($price, 2));
        } else {
            return sprintf('%.2f', round($price, 2));
        }
    }

    public function formatZone($time, $toZone, $fromZone = null, $format = 'Y-m-d H:i:s')
    {
        return (string)dateFormat(
            getZoneByCountry($fromZone),
            getZoneByCountry($toZone),
            $time,
            $format
        );
    }

    /**
     * 格式化价格
     * @param $number
     * @param string|null $currency
     * @param array $config
     * @return string
     */
    public function formatPrice($number, string $currency = null, array $config = [])
    {
        $config['currency'] = $currency;
        return CurrencyHelper::formatPrice($number, $config);
    }

    /**
     * 将时间分行
     * 例如:2020-04-29 19:00:48
     * @param $time
     * @param string|null $class1 第一行类名称
     * @param string|null $class2 第二行类名称
     */
    public function formatSpTime($time, $class1 = null, $class2 = null)
    {
        $string = <<<HTML
<p class="{1}">{0}</p><p class="{3}">{2}</p>
HTML;
        if (preg_match('/([\d]{4}-[\d]{1,2}-[\d]{1,2}) ([\d]{1,2}:[\d]{1,2}(:[\d]{1,2})?)/', $time, $matches)) {
            return dprintf($string, [$matches[1], $class1, $matches[2], $class2]);
        }
        return $time;
    }
}
