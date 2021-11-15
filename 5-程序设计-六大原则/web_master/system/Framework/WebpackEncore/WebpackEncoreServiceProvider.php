<?php

namespace Framework\WebpackEncore;

use Framework\DI\DeferrableProvider;
use Framework\DI\ServiceProvider;
use Framework\Foundation\Application;
use Framework\WebpackEncore\Asset\EntrypointFinder;

class WebpackEncoreServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * @inheritDoc
     */
    public function register()
    {
        $this->app->singleton('webpack-encore.finder', function (Application $app) {
            $config = $app->config->get('webpack_encore');
            return new EntrypointFinder($app->pathAliases->get($config['entrypointJsonPath']), $config['strictMode']);
        });
    }

    /**
     * @inheritDoc
     */
    public function alias()
    {
        return [
            'webpack-encore.finder' => [EntrypointFinder::class],
        ];
    }
}
