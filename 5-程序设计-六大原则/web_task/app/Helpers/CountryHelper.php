<?php

namespace App\Helpers;

class CountryHelper
{
    /**
     * 获取时区
     * @param $countryId
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
            'DEU' => 81,
            'JPN' => 107,
            'GBR' => 222,
            'USA' => 223
        ];
        return $map[$code] ?? $default;
    }

    /**
     * 根据国家名字 获取时区
     *
     * @param $countryName
     * @param null $default
     * @return string|null
     */
    public static function getTimezoneByName($countryName, $default = null)
    {
        $map = [
            'DEU' => 81, // 德国
            'JPN' => 107, // 日本
            'GBR' => 222, // 英国
            'USA' => 223, // 美国
        ];
        if (!array_key_exists(strtoupper($countryName), $map)) {
            return $default;
        }
        return self::getTimezone($map[strtoupper($countryName)], $default);
    }

    /**
     * 通过国别ID获取国别代号
     *
     * @param $countryId int 国家ID
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
}