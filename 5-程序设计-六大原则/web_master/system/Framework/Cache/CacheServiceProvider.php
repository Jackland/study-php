<?php

namespace Framework\Cache;

use Framework\DI\DeferrableProvider;
use Framework\DI\ServiceProvider;
use Framework\Foundation\Application;
use Psr\Cache\CacheItemInterface;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Cache\Psr16Cache;

class CacheServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * @inheritDoc
     */
    public function register()
    {
        $this->app->singleton('cache.manager', function ($app) {
            return new CacheManager($app);
        });

        $this->app->singleton('cache.psr6', function ($app) {
            return $app['cache.manager']->driver();
        });

        $this->app->singleton('cache.psr16', function (Application $app) {
            return new Psr16Cache($app['cache.psr6']);
        });

        $this->app->singleton('cache', function (Application $app) {
            return new Cache($app['cache.psr16'], $app['log']);
        });
    }

    /**
     * @inheritDoc
     */
    public function alias()
    {
        return [
            'cache' => [Cache::class],
            'cache.psr6' => [CacheItemInterface::class],
            'cache.psr16' => [CacheInterface::class],
            'cache.manager' => [CacheManager::class],
        ];
    }
}
