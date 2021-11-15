<?php

namespace App\Providers;

use Framework\DI\DeferrableProvider;
use Framework\DI\ServiceProvider;
use Framework\Foundation\Application;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\RedisStore;
use Symfony\Component\Lock\Store\RetryTillSaveStore;

class LockServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * @inheritDoc
     */
    public function register()
    {
        $this->app->singleton(LockFactory::class, function (Application $app) {
            // 因为 FlockStore 和 RedisStore 的功能存在差异，因此固定使用 redis 锁
            // 详见: https://symfony.com/doc/4.4/components/lock.html#available-stores
            $store = new RedisStore($app->get('redis')->driver()->getClient());
            $store = new RetryTillSaveStore($store);

            return new LockFactory($store);
        });
    }

    /**
     * @inheritDoc
     */
    public function provides()
    {
        return [LockFactory::class];
    }
}
