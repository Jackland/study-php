<?php

namespace Framework\Event;

use Framework\DI\ServiceProvider;
use Illuminate\Contracts\Events\Dispatcher as DispatcherContract;
use Illuminate\Events\Dispatcher;

class EventServiceProvider extends ServiceProvider
{
    /**
     * @inheritDoc
     */
    public function register()
    {
        $this->app->singleton('events', function ($app) {
            return (new Dispatcher($app))
                // 暂时不支持 queue，后续增加
                //->setQueueResolver()
                ;
        });
    }

    /**
     * @inheritDoc
     */
    public function alias()
    {
        return [
            'events' => [Dispatcher::class, DispatcherContract::class],
        ];
    }
}
