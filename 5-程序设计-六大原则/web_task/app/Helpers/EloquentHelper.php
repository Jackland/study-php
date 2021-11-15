<?php

namespace App\Helpers;

use Illuminate\Database\Query\Builder;

class EloquentHelper
{
    /**
     * 通过ORM生成SQL
     *
     * @param Builder $builder
     * @return string|string[]|null
     */
    public static function getCompleteSql(Builder $builder)
    {
        $bindings = $builder->getBindings();
        $i = 0;
        return preg_replace_callback('/\?/', function ($matches) use ($bindings, &$i) {
            return "'" . addslashes($bindings[$i++] ?? '') . "'";
        }, $builder->toSql());
    }
}