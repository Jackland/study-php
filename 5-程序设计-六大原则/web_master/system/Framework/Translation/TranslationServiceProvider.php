<?php

namespace Framework\Translation;

use Framework\DI\DeferrableProvider;
use Framework\DI\ServiceProvider;
use Framework\Foundation\Application;
use Illuminate\Contracts\Translation\Loader;
use Illuminate\Contracts\Translation\Translator as TranslatorContract;
use Illuminate\Translation\FileLoader;
use Illuminate\Translation\Translator as LaravelTranslator;

class TranslationServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * @inheritDoc
     */
    public function register()
    {
        $this->registerLoader();

        $this->app->singleton('translator', function (Application $app) {
            $loader = $app['translator.loader'];
            $locale = $app->config->get('translation.locale');
            $defaultCategory = $app->config->get('translation.default_category', 'app');

            $translator = new Translator($loader, $locale, $defaultCategory);
            $translator->setFallback($app->config->get('translation.fallback_locale'));

            return $translator;
        });
    }

    /**
     * @inheritDoc
     */
    public function alias()
    {
        return [
            'translator' => [TranslatorContract::class, Translator::class, LaravelTranslator::class],
            'translator.loader' => [Loader::class],
        ];
    }

    protected function registerLoader()
    {
        $this->app->singleton('translator.loader', function (Application $app) {
            return new FileLoader($app['files'], $app->pathAliases->get($app->config['translation.path']));
        });
    }
}
