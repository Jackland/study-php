<?php

namespace Framework\Foundation\Traits;

use Framework\DI\ServiceProvider;
use Illuminate\Support\Arr;

trait ProviderTrait
{
    /**
     * All of the registered service providers.
     *
     * @var \Illuminate\Support\ServiceProvider[]
     */
    protected $serviceProviders = [];

    /**
     * The names of the loaded service providers.
     *
     * @var array
     */
    protected $loadedProviders = [];

    /**
     * 注册服务
     * @param ServiceProvider|string $provider
     * @param false $force
     * @return ServiceProvider|null
     */
    public function register($provider, $force = false)
    {
        if (($registered = $this->getProvider($provider)) && !$force) {
            return $registered;
        }

        if (is_string($provider)) {
            $provider = $this->resolveProvider($provider);
        }

        $provider->register();
        foreach ($provider->bindings as $key => $value) {
            $this->bind($key, $value);
        }
        foreach ($provider->singletons as $key => $value) {
            $this->singleton($key, $value);
        }
        if ($alias = $provider->alias()) {
            $this->registerServiceProviderAlias($alias);
        }

        $this->markAsRegistered($provider);

        if ($this->isBooted()) {
            $this->bootProvider($provider);
        }

        return $provider;
    }

    /**
     * Get the registered service provider instance if it exists.
     *
     * @param ServiceProvider|string $provider
     * @return ServiceProvider|null
     */
    public function getProvider($provider)
    {
        return array_values($this->getProviders($provider))[0] ?? null;
    }

    /**
     * Get the registered service provider instances if any exist.
     *
     * @param ServiceProvider|string $provider
     * @return array
     */
    public function getProviders($provider)
    {
        $name = is_string($provider) ? $provider : get_class($provider);

        return Arr::where($this->serviceProviders, function ($value) use ($name) {
            return $value instanceof $name;
        });
    }

    /**
     * @param string $provider
     * @return ServiceProvider
     */
    public function resolveProvider($provider)
    {
        return new $provider($this);
    }

    /**
     * @param ServiceProvider $provider
     * @return void
     */
    protected function markAsRegistered($provider)
    {
        $this->serviceProviders[] = $provider;

        $this->loadedProviders[get_class($provider)] = true;
    }

    /**
     * 注册服务 alias
     */
    protected function registerServiceProviderAlias($aliases)
    {
        foreach ($aliases as $key => $all) {
            foreach ((array)$all as $item) {
                $this->alias($key, $item);
            }
        }
    }
}
