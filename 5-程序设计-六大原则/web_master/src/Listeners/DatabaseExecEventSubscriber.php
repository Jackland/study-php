<?php

namespace App\Listeners;

use App\Logging\Logger;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Database\Events\TransactionRolledBack;

class DatabaseExecEventSubscriber
{
    public function subscribe(Dispatcher $dispatcher)
    {
        // sql 记录
        $dispatcher->listen(QueryExecuted::class, QueryExecutedListener::class);
        // 事务记录
        $dispatcher->listen(TransactionBeginning::class, function () {
            Logger::orm('transaction start');
        });
        $dispatcher->listen(TransactionCommitted::class, function () {
            Logger::orm('transaction committed');
        });
        $dispatcher->listen(TransactionRolledBack::class, function () {
            Logger::orm('transaction rolled back');
        });
    }
}
