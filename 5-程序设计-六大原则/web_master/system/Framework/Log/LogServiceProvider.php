<?php

namespace Framework\Log;

use Framework\DI\ServiceProvider;
use Framework\Foundation\Application;

class LogServiceProvider extends ServiceProvider
{
    /**
     * @inheritDoc
     */
    public function register()
    {
        $this->app->singleton('log', function (Application $app) {
            $config = $app->config;
            return new LogManager(
                $config->get('logging.channels'),
                $config->get('logging.defaultChannel', 'app'),
                $this->app->pathAliases,
                $config->get('logging.formatters', []),
                $config->get('logging.processors', []),
                $this->app['events']
            );
        });
    }

    /**
     * @inheritDoc
     */
    public function alias()
    {
        return [
            'log' => [LogManager::class, \Psr\Log\LoggerInterface::class]
        ];
    }
}
