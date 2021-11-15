<?php

namespace Framework\Redis;

use Framework\DI\DeferrableProvider;
use Framework\DI\ServiceProvider;

class RedisServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * @inheritDoc
     */
    public function register()
    {
        $this->app->singleton('redis', function ($app) {
            return new RedisManager($app);
        });
    }

    /**
     * @inheritDoc
     */
    public function alias()
    {
        return [
            'redis' => [RedisManager::class],
        ];
    }
}
