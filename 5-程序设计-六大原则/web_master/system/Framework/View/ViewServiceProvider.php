<?php

namespace Framework\View;

use Framework\Aliases\Aliases;
use Framework\DI\ServiceProvider;
use Framework\Foundation\Application;
use Framework\View\Assets\AssetManager;
use Framework\View\Assets\AssetPublisher;
use Illuminate\Filesystem\Filesystem;
use Twig_Autoloader;
use Twig_Environment;
use Twig_Loader_Filesystem;

class ViewServiceProvider extends ServiceProvider
{
    /**
     * @inheritDoc
     */
    public function register()
    {
        $this->registerFactory();
        $this->registerViewAliases();
        $this->registerFinder();
        $this->registerRenderer();
        $this->registerAssetManager();
    }

    /**
     * @inheritDoc
     */
    public function alias()
    {
        return [
            'view' => [ViewFactory::class],
            'view.finder' => [ViewFinderInterface::class, ViewFinder::class],
            'view.asset' => [AssetManager::class],
            'view.renderer.twig' => [TwigRenderer::class],
            'view.renderer.php' => [PhpRenderer::class],
        ];
    }

    protected function registerFactory()
    {
        $this->app->singleton('view', function (Application $app) {
            $view = new ViewFactory(
                $app,
                $app['view.finder'],
                [
                    'twig' => 'view.renderer.twig',
                    'php' => 'view.renderer.php',
                ],
                'twig'
            );

            $view->share([
                'this' => $view,
                'app' => $app,
            ]);

            $view->setAssetManager($app['view.asset']);

            return $view;
        });
    }

    protected function registerViewAliases()
    {
        $this->app->singleton('view.aliases', function (Application $app) {
            $config = $app->config->get('view.aliases', []);
            $alias = new Aliases();
            foreach ($config as $key => $value) {
                $alias->set($key, $app->pathAliases->get($value));
            }
            return $alias;
        });
    }

    protected function registerFinder()
    {
        $this->app->singleton('view.finder', function (Application $app) {
            $config = $app->config->get('view.finder', []);
            return new ViewFinder(
                $app->pathAliases->get($config['base_path']),
                $config['theme_paths'] ?? [],
                $app['view.aliases']
            );
        });
    }

    protected function registerRenderer()
    {
        $this->registerTwigRenderer();
        $this->registerPhpRenderer();
    }

    protected function registerAssetManager()
    {
        $this->app->singleton('view.asset', function (Application $app) {
            $publisher = new AssetPublisher(
                $app->config['view.asset.base_path'],
                $app->config['view.asset.base_url'],
                $app->pathAliases,
                $app->has('files') ? $app->get('files') : new Filesystem()
            );
            $publisher->setAppendTimestamp($app->config['view.asset.append_timestamp']);
            $publisher->setForceCopy($app->config['view.asset.force_copy']);
            return new AssetManager($publisher);
        });
    }

    protected function registerTwigRenderer()
    {
        include_once(DIR_SYSTEM . 'library/template/Twig/Autoloader.php');
        Twig_Autoloader::register();
        $this->app->singleton('view.renderer.twig', function (Application $app) {
            $loader = new Twig_Loader_Filesystem();

            $loaderPath = array_merge(
                $app->config->get('view.renderer.twig.loader_paths', []),
                array_map(function ($key) {
                    return substr($key, 1); // 移除@
                }, array_flip($app['view.aliases']->getAll()))
            );

            foreach ($loaderPath as $path => $namespace) {
                $path = $app->pathAliases->get($path);
                if ($namespace) {
                    $loader->addPath($path, $namespace);
                } else {
                    $loader->addPath($path);
                }
            }

            $options = $app->config->get('view.renderer.twig.env_options', []);
            if (isset($options['cache_enable']) && $options['cache_enable']) {
                $options['cache'] = $app->pathAliases->get($options['cache'] ?? '@runtime/cache/twig');
                unset($options['cache_enable'], $options['cache_path']);
            }

            $environment = new Twig_Environment($loader, $options);

            foreach ($app->config->get('view.renderer.twig.extensions', []) as $extClass) {
                $environment->addExtension($this->app->make($extClass));
            }

            return new TwigRenderer($environment);
        });
    }

    protected function registerPhpRenderer()
    {
        $this->app->singleton('view.renderer.php', function () {
            return new PhpRenderer();
        });
    }
}
