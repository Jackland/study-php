<?php

namespace App\Providers;

use App\Components\Replace\Worker;
use Illuminate\Contracts\Debug\ExceptionHandler;

class QueueServiceProvider extends \Illuminate\Queue\QueueServiceProvider
{
    /**
     * @inheritDoc
     */
    protected function registerWorker()
    {
        $this->app->singleton('queue.worker', function () {
            // 替换 worker 的实现
            return new Worker(
                $this->app['queue'], $this->app['events'], $this->app[ExceptionHandler::class]
            );
        });
    }
}