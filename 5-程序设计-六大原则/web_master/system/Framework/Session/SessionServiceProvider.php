<?php

namespace Framework\Session;

use Framework\DI\ServiceProvider;

class SessionServiceProvider extends ServiceProvider
{
    /**
     * @inheritDoc
     */
    public function register()
    {
        $this->app->singleton('session.manager', function ($app) {
            return new SessionManager($app);
        });

        $this->app->singleton('session', function ($app) {
            return $app['session.manager']->driver();
        });
    }

    /**
     * @inheritDoc
     */
    public function alias()
    {
        return [
            'session.manager' => [SessionManager::class],
            'session' => [Session::class, \Framework\Http\Session::class],
        ];
    }
}
