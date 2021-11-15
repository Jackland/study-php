<?php

namespace App\Helper;

class CountryHelper
{
    /**
     * 获取当前的国别符号
     * @return string
     */
    public static function getCurrentCode(): string
    {
        return session('country', 'USA');
    }

    /**
     * 获取当前国别 id
     * @return int|null
     */
    public static function getCurrentId()
    {
        $code = static::getCurrentCode();
        return static::getCountryByCode($code);
    }

    /**
     * 获取时区
     * @param int $countryId
     * @param null $default
     * @return string|null
     */
    public static function getTimezone($countryId, $default = null)
    {
        $map = [
            81 => 'Europe/Berlin', // 德国
            107 => 'Asia/Tokyo', // 日本
            222 => 'Europe/London', // 英国
            223 => 'America/Los_Angeles', // 美国
        ];
        return $map[$countryId] ?? $default;
    }

    /**
     * 通过国别代号获取国别ID
     *
     * @param $code
     * @param null $default
     * @return mixed|null
     */
    public static function getCountryByCode($code, $default = null)
    {
        $map = [
            'DEU' => 81, // 德国
            'JPN' => 107, // 日本
            'GBR' => 222, // 英国
            'USA' => 223, // 美国
        ];
        return $map[strtoupper($code)] ?? $default;
    }

    /**
     * 根据国家名字 获取时区
     *
     * @param string $countryCode
     * @param null $default
     * @return string|null
     */
    public static function getTimezoneByCode($countryCode, $default = null)
    {
        $id = static::getCountryByCode($countryCode);
        if (!$id) {
            return $default;
        }
        return self::getTimezone($id, $default);
    }

    /**
     * 根据国家名字 获取时区
     *
     * @param string $countryName
     * @param null $default
     * @return string|null
     */
    public static function getTimezoneByName($countryName, $default = null)
    {
        $countryId = self::getCountryByCode(strtoupper($countryName));
        if (!$countryId) {
            return $default;
        }
        return self::getTimezone($countryId, $default);
    }

    /**
     * 通过国别ID获取国别代号
     *
     * @param int $countryId  国家ID
     * @param null $default
     * @return mixed|null
     */
    public static function getCountryCodeById($countryId, $default = null)
    {
        $map = [
            81 => 'DEU',
            107 => 'JPN',
            222 => 'GBR',
            223 => 'USA'
        ];

        return $map[$countryId] ?? $default;
    }

    /**
     * 通过国别ID获取国家名称
     *
     * @param int $countryId 国家ID
     * @param null $default
     * @return mixed|null
     */
    public static function getCountryNameById($countryId, $default = null)
    {
        $map = [
            81 => __('德国', [], 'common'),
            107 => __('日本', [], 'common'),
            222 => __('英国', [], 'common'),
            223 => __('美国', [], 'common')
        ];

        return $map[$countryId] ?? $default;
    }

    /**
     * 通过国别ID获取国家货币单位
     *
     * @param int $countryId 国家ID
     * @param null $default
     * @return mixed|null
     */
    public static function getCurrencyUnitNameById($countryId, $default = null)
    {
        $map = [
            81 => 'EUR',
            107 => 'JPY',
            222 => 'GBP',
            223 => 'USD'
        ];

        return $map[$countryId] ?? $default;
    }

    /**
     * 通过国别ID获取货币代号
     *
     * @param int $countryId
     * @param string $default
     * @return string
     */
    public static function getCountryCurrencyCodeById($countryId, $default = 'USD'): string
    {
        $map = [
            81 => 'EUR',
            107 => 'JPY',
            222 => 'GBP',
            223 => 'USD',
        ];

        return $map[$countryId] ?? $default;
    }
}
