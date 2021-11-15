<?php

namespace App\Components\Rules;

class RulesScanner
{
    public static function getExtensionRules()
    {
        return [
            ExtensionRule::class,
            CellphoneRule1::class,
        ];
    }
}
