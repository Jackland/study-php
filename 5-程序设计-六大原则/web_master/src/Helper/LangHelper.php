<?php

namespace App\Helper;

use App\Enums\Common\LangLocaleEnum;

class LangHelper
{
    /**
     * 获取当前选择的语言
     * @return string LangLocaleEnum 的值
     */
    public static function getCurrentCode(): string
    {
        return session('lang', LangLocaleEnum::getDefault());
    }

    /**
     * 是否是中文
     * @return bool
     */
    public static function isChinese(): bool
    {
        return static::getCurrentCode() === LangLocaleEnum::ZH_CN;
    }
}
