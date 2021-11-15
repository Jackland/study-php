<?php

namespace App\Enums\Common;

use Framework\Enum\BaseEnum;

class LangLocaleEnum extends BaseEnum
{
    const EN_GB = 'en-gb';
    const ZH_CN = 'zh-cn';

    public static function getDefault()
    {
        return static::EN_GB;
    }

    public static function getFallback()
    {
        return static::EN_GB;
    }

    public static function getCarbonLocale($lang, $default = 'en')
    {
        $map = [
            static::EN_GB => 'en',
            static::ZH_CN => 'zh',
        ];
        return $map[$lang] ?? $default;
    }
}
