<?php

namespace App\Components;

use App\Components\UniqueGenerator\DateGenerator;
use App\Components\UniqueGenerator\GlobalGenerator;

class UniqueGenerator
{
    /**
     * 按日生成
     * @return DateGenerator
     */
    public static function date(): DateGenerator
    {
        return new DateGenerator();
    }

    /**
     * 全局生成
     * @return GlobalGenerator
     */
    public static function global(): GlobalGenerator
    {
        return new GlobalGenerator();
    }
}
