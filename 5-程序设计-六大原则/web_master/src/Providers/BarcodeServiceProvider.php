<?php

namespace App\Providers;

use App\Components\BarcodeGenerator;
use Framework\DI\DeferrableProvider;
use Framework\DI\ServiceProvider;

class BarcodeServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * @inheritDoc
     */
    public function register()
    {
        $this->app->bind('barcode', BarcodeGenerator::class);
    }

    /**
     * @inheritDoc
     */
    public function provides()
    {
        return [
            'barcode'
        ];
    }
}
