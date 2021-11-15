<?php

namespace App\Providers;

use App\Components\AES;
use Framework\DI\ServiceProvider;

class DBEncryptServiceProvider extends ServiceProvider
{
    /**
     * @inheritDoc
     */
    public function register()
    {
        $this->app->singleton('db-aes', function () {
            $aes = new AES(
                get_env('DB_AES_KEY', 'UQ1f0Y61vjwlewLYB0p7wqtp3xy4xkXuqS6/qOYI4nY='),
                get_env('DB_AES_IV', 'VfyMG61amfSixw577V/RYA==')
            );
            return new AES\DBAES($aes);
        });
    }
}
