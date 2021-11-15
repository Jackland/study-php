<?php

namespace Framework\Route;

use Framework\DI\ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * @inheritDoc
     */
    public function register()
    {
        $this->app->singleton(OcRouter::class);
    }
}
