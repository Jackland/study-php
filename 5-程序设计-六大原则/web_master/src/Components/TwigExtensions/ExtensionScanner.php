<?php

namespace App\Components\TwigExtensions;

class ExtensionScanner
{
    public static function getList()
    {
        return [
            AssetExtension::class,
            CountryBasedExtension::class,
            ImageExtension::class,
            RouteExtension::class,
            StringExtension::class,
            ArrayExtension::class,
            TranslationExtension::class,
            ViewExtension::class,
            JsVarExtension::class,
            AesExtension::class,
            WebpackEncoreEntryExtension::class,
        ];
    }
}
