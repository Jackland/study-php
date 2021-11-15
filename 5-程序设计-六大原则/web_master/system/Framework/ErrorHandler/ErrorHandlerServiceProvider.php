<?php

namespace Framework\ErrorHandler;

use Framework\DI\ServiceProvider;
use Framework\ErrorHandler\handlers\HiddenHandler;
use Framework\ErrorHandler\handlers\WhoopsHandler;

class ErrorHandlerServiceProvider extends ServiceProvider
{
    /**
     * @inheritDoc
     */
    public function register()
    {
        $this->app->singleton(ErrorHandlerInterface::class, OC_DEBUG ? $this->registerDebugHandler() : $this->registerProdHandler());

        // 立即注册监听
        $this->app->get(ErrorHandlerInterface::class)->register();
    }

    protected function registerDebugHandler()
    {
        return function ($app) {
            return new WhoopsHandler($app);
        };
    }

    protected function registerProdHandler()
    {
        return function ($app) {
            return new HiddenHandler($app);
        };
    }
}
