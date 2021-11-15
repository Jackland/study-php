<?php

namespace App\Providers;

use App\Components\Rules\ExtendableInterface;
use App\Components\Rules\RulesScanner;
use Framework\DI\DeferrableProvider;
use Framework\DI\ServiceProvider;
use Framework\Foundation\Application;
use Illuminate\Contracts\Validation\Factory as FactoryContract;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Factory;

class ValidationServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * @inheritDoc
     */
    public function register()
    {
        $this->app->singleton('validator', function (Application $app) {
            return new Factory($app['translator'], $app);
        });

        $this->app->resolving('validator', function (FactoryContract $validator) {
            // 扩展校验规则
            foreach (RulesScanner::getExtensionRules() as $extensionRule) {
                if (!is_a($extensionRule, ExtendableInterface::class, true)) {
                    continue;
                }
                $validator->extend($extensionRule::name(), function (string $attribute, $value, array $parameters, Validator $validator) use ($extensionRule) {
                    return $extensionRule::validate($attribute, $value, $parameters, $validator);
                });
                $validator->replacer($extensionRule::name(), function (string $message, string $attribute, string $rule, array $parameters, Validator $validator) use ($extensionRule) {
                    return $extensionRule::replacerMessage($message, $attribute, $rule, $parameters, $validator);
                });
            }
        });
    }

    /**
     * @inheritDoc
     */
    public function alias()
    {
        return [
            'validator' => [FactoryContract::class],
        ];
    }
}
