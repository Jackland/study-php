<?php

namespace Framework\Debug;

use DebugBar\HttpDriverInterface;
use Framework\DI\ServiceProvider;
use Framework\Foundation\Application;
use Framework\Helper\StringHelper;

class DebugServiceProvider extends ServiceProvider
{
    /**
     * @inheritDoc
     */
    public function register()
    {
        $this->registerHttpDriver();

        $this->app->singleton('debugbar', function (Application $app) {
            $config = $app->config['debugbar'];
            if ($app->isConsole()) {
                // console 不支持
                $config['enable'] = false;
            }
            return new DebugBar($app, $config);
        });

        $this->app->resolving('debugbar', function (DebugBar $debugBar) {
            if ($debugBar->isEnabled()) {
                $route = request('route', 'common/home');
                foreach ($debugBar->getExceptRoutes() as $except) {
                    if (StringHelper::matchWildcard($except, $route)) {
                        $debugBar->disable();
                        break;
                    }
                }
                if ($debugBar->isEnabled()) {
                    $debugBar->boot();
                }
            }
        });
    }

    /**
     * @inheritDoc
     */
    public function alias()
    {
        return [
            'debugbar' => [DebugBar::class],
        ];
    }

    protected function registerHttpDriver()
    {
        $this->app->singleton(HttpDriverInterface::class, function (Application $app) {
            return new HttpDriver($app['session'], $app->ocRegistry->get('response'));
        });
    }
}
