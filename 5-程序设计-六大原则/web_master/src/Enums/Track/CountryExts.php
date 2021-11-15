<?php

namespace App\Enums\Track;

use Framework\Enum\BaseEnum;

/**
 * oc_country_exts 表的 type 字段
 *
 * Class CountryExts
 * @package App\Enums\Track
 */
class CountryExts extends BaseEnum
{
    const COMMON = 1; // 普通
    const LTL = 2; // 超大件
}
