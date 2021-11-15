<?php

namespace App\Listeners;

use App\Logging\Logger;
use Illuminate\Database\Events\QueryExecuted;

class QueryExecutedListener
{
    private static $full = 0;
    private static $count = 0;

    public function handle(QueryExecuted $event)
    {
        $sql = $event->sql;
        if ($event->bindings) {
            foreach ($event->bindings as $v) {
                $sql = preg_replace('/\\?/', "'" . (is_string($v) ? addslashes($v) : $v) . "'", $sql, 1);
            }
        }
        $min_exec_time = defined('DB_SQL_RECORD_MIN_TIME') ? DB_SQL_RECORD_MIN_TIME : 1500;
        static::$full += $event->time;
        static::$count++;
        if ($event->time > $min_exec_time or (defined('DEBUG') && DEBUG == 1) or (isset($_REQUEST['DEBUG']) and $_REQUEST['DEBUG'] == 1)) {
            Logger::orm('[{time}ms][{full}ms/{count}] {sql} [route:{route}]', 'info', [
                'time' => $event->time,
                'full' => static::$full,
                'count' => static::$count,
                'sql' => $sql,
                'route' => request('route'),
            ]);
        } else {
            if (preg_match("/^\s*(update|delete|insert)\s*/i", $sql)) {
                if (!preg_match("/^(\s*\b\w*\b\s*){1,2}`?\b(oc_cart|oc_user|oc_session|oc_request_log.*?)\b`?/i", $sql)) {
                    Logger::orm('[{time}ms][{full}ms/{count}] {sql} [route:{route}]', 'info', [
                        'time' => $event->time,
                        'full' => static::$full,
                        'count' => static::$count,
                        'sql' => $sql,
                        'route' => request('route'),
                    ]);
                }
            }
        }
    }
}
