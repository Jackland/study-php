<?php

namespace Framework\IdeHelper;

use Framework\DI\DeferrableProvider;
use Framework\DI\ServiceProvider;
use Framework\IdeHelper\Console\MetaCommand;
use Framework\IdeHelper\Console\ModelsCommand;
use Framework\View\LaravelMock\Factory;
use Framework\View\PhpRenderer;
use Framework\View\ViewFactory;
use Framework\View\ViewFinder;

class IdeHelperServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * @inheritDoc
     */
    public function register()
    {
        if (!class_exists('Barryvdh\LaravelIdeHelper\IdeHelperServiceProvider')) {
            return;
        }

        $localViewFactory = $this->createLocalViewFactory();

        $this->app->config['ide-helper'] = array_merge(require $this->app->pathAliases->get('@vendor/barryvdh/laravel-ide-helper/config/ide-helper.php'), [
            'helper_files' => [],
            'write_model_magic_where' => false,
            'model_locations' => ['src/Models'],
            'extra' => [
                'Eloquent' => [],
            ],
            'include_class_docblocks' => true,
            'ignored_models' => [
                'App\Models\Base\OcModel',
                'App\Models\Base\CustomerPartnerModel',
                'App\Models\Base\TbSysModel',
            ],
        ]);

        // facade 不支持
        /*$this->app->singleton('command.ide-helper.generate', function ($app) use ($localViewFactory) {
            return new GeneratorCommand($app['config'], $app['files'], $localViewFactory);
        });*/

        $this->app->singleton('command.ide-helper.meta', function ($app) use ($localViewFactory) {
            return new MetaCommand($app['files'], $localViewFactory, $app['config']);
        });

        $this->app->singleton('command.ide-helper.models', function ($app) {
            return new ModelsCommand($app['files']);
        });

        $this->commands([
            'command.ide-helper.meta',
            'command.ide-helper.models'
        ]);
    }

    /**
     * @inheritDoc
     */
    public function alias()
    {
        return [
            'command.ide-helper.meta' => [MetaCommand::class],
            'command.ide-helper.models' => [ModelsCommand::class],
        ];
    }

    /**
     * @return Factory|\Illuminate\View\Factory
     */
    protected function createLocalViewFactory()
    {
        $finder = new ViewFinder($this->app->pathAliases->get('@vendor/barryvdh/laravel-ide-helper/resources/views'));
        $phpRenderer = new PhpRenderer();
        $factory = new ViewFactory(
            $this->app,
            $finder,
            [
                'php' => $phpRenderer,
            ],
            'php');

        return new Factory($factory);
    }
}
