<?php

namespace App\Providers;

use App\Components\Pdf\DomPdfGenerator;
use Dompdf\Dompdf;
use Dompdf\Options;
use Framework\DI\DeferrableProvider;
use Framework\DI\ServiceProvider;
use Framework\Foundation\Application;

class DomPdfServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * @inheritDoc
     */
    public function register()
    {
        $this->app->singleton('dompdf.options', function () {
            // 默认全局配置可以加载此处，如果复杂可单独文件配置：$app->config->get('dompdf.options', []);
            $options = [];
            return new Options($options);
        });

        $this->app->bind('dompdf.instance', function (Application $app) {
            $options = $app->get('dompdf.options');
            $dompdf = new Dompdf($options);
            $dompdf->setBasePath($app->pathAliases->get('@public'));
            return $dompdf;
        });

        $this->app->bind('dompdf', function (Application $app) {
            return new DomPdfGenerator($app->get('dompdf.instance'), $app->get('files'));
        });
    }

    /**
     * @inheritDoc
     */
    public function provides()
    {
        return [
            'dompdf.options',
            'dompdf.instance',
            'dompdf',
        ];
    }
}
