<?php

namespace App\Helper;

class TranslationHelper
{
    protected static $needChangeTransLocale = null;
    protected static $originTransLang = null;

    /**
     * 临时开启翻译
     */
    public static function tempEnable()
    {
        self::$originTransLang = trans()->getLocale();
        $sessionLang = session('lang');
        self::$needChangeTransLocale = $sessionLang !== self::$originTransLang;
        if (self::$needChangeTransLocale) {
            trans()->setLocale($sessionLang);
        }
    }

    /**
     * 关闭临时开启的翻译
     */
    public static function tempDisableAfterEnable()
    {
        if (self::$needChangeTransLocale === true) {
            trans()->setLocale(self::$originTransLang);
        }
    }
}
