<?php

namespace App\Repositories\Common;

use App\Components\UniqueGenerator;

class SerialNumberRepository
{
    /**
     * 获取时间类型编号
     *
     * @param string $service ServiceEnum.xxx 必须先行配置
     * @param int $digit 长度（去除时间和前缀之后的长度）
     * @param string $prefix 编号前缀
     * @param int $countryId 指定国别ID(默认自动获取当前国别)
     * @param bool $checkUnique 是否需要保持唯一(检测对应数据表字段)
     * @param bool $isFullYear 年的为数：true:4 false:2
     * @return string
     */
    public static function getDateSerialNumber(string $service, int $digit = 8, string $prefix = '', bool $checkUnique = true, int $countryId = AMERICAN_COUNTRY_ID, bool $isFullYear = true)
    {
        $sn = UniqueGenerator::date()
            ->service($service)
            ->digit($digit);

        // 指定国别
        if ($countryId) {
            $sn->country($countryId);
        }
        // 设置前缀
        if ($prefix) {
            $sn->prefix($prefix);
        }
        // 需要检测唯一
        if ($checkUnique) {
            $sn->checkDatabase();
        }
        // 是否需要四位年
        if ($isFullYear) {
            $sn->fullYear();
        }

        return $sn->random();
    }

    /**
     * 获取对应业务唯一编号
     *
     * @param string $service ServiceEnum.xxx 必须先行配置
     * @param int $digit 长度（去除前缀之后的长度）
     * @param string $prefix 前缀
     * @param bool $fistNoZero 首位是否不为0
     * @return string
     */
    public static function getGlobalSerialNumber(string $service, int $digit = 6, string $prefix = '', bool $fistNoZero = true)
    {
        $un = UniqueGenerator::global()
            ->service($service)
            ->digit($digit);

        // 设置前缀
        if ($prefix) {
            $un->prefix($prefix);
        }
        // 首位不为0
        if ($fistNoZero) {
            $un->randomFirstNoZero();
        }

        return $un->random();
    }
}