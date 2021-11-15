<?php

namespace App\Components\Debug;

use App\Models\Setting\Setting;
use Illuminate\Database\Query\Expression;

class DebugBarDatabaseMarker
{
    /**
     * 在 debugBar 的栏目下添加一个标志位，用于排查 sql
     * @param string $name
     */
    public static function mark($name)
    {
        if (!debugBar()->isEnabled()) {
            return;
        }

        Setting::query()->select("key as '-------------- {$name} ---------------'")
            ->where(new Expression('1=0'))
            ->get();
    }
}
