<?php

namespace Framework\Storage;

use Framework\DI\DeferrableProvider;
use Framework\DI\ServiceProvider;
use Framework\Foundation\Application;
use Illuminate\Filesystem\Filesystem;

class StorageServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * @inheritDoc
     */
    public function register()
    {
        $this->app->singleton('files', function () {
            return new Filesystem();
        });

        $this->app->singleton('storage.manager', function (Application $app) {
            return new StorageManager($app->config->get('storage.disks'));
        });
    }

    /**
     * @inheritDoc
     */
    public function alias()
    {
        return [
            'files' => [Filesystem::class],
            'storage.manager' => [StorageManager::class],
        ];
    }
}
