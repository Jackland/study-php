<?php

namespace App\Providers;

use App\Components\Pdf\MPdfGenerator;
use Framework\DI\DeferrableProvider;
use Framework\DI\ServiceProvider;
use Framework\Foundation\Application;

class MPdfServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * @inheritDoc
     */
    public function register()
    {
        $this->app->bind('mpdf', function (Application $app) {
            return new MPdfGenerator([], $app->get('files'));
        });
        $this->app->bind('mpdf.instance', function (Application $app) {
            return $app->get('mpdf')->getMpdf();
        });
    }

    /**
     * @inheritDoc
     */
    public function provides()
    {
        return [
            'mpdf',
            'mpdf.instance',
        ];
    }
}
