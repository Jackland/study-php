<?php

namespace App\Listeners;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Log;

/**
 * Class QueryListener
 * @package App\Listeners
 */
class QueryListener
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param QueryExecuted $event
     * @return void
     */
    public function handle(QueryExecuted $event)
    {
        if ($event->time > config('app.db_sql_log.min_time') || config('app.db_sql_log.enable')) {
            $this->writeLog($event);
        } else {
            if (preg_match("/^\s*(update|delete|insert)\s*/i", $event->sql)) {
                if (!preg_match("/^(\s*\b\w*\b\s*){1,2}`?\b(oc_cart|oc_user|oc_message_content)\b`?/i", $event->sql)) {
                    $this->writeLog($event);
                }
            }
        }
    }

    /**
     * @param QueryExecuted $event
     * @param void
     */
    public function writeLog(QueryExecuted $event)
    {
        $tmp = str_replace('?', '"' . '%s' . '"', $event->sql);
        $qBindings = [];
        foreach ($event->bindings as $key => $value) {
            if (is_numeric($key)) {
                $qBindings[] = $value;
            } else {
                $tmp = str_replace(':' . $key, '"' . $value . '"', $tmp);
            }
        }
        $qBindings && $tmp = vsprintf($tmp, $qBindings);
        $tmp = str_replace("\\", "", $tmp);
        Log::info('[' . $event->connectionName . '][' . $event->time . 'ms] ' . $tmp . ' ');
    }
}
