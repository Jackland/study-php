<?php

namespace Framework\Cache;

use Framework\Foundation\Application;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;

class CacheManager
{
    private $app;

    protected $drivers = [];

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function driver($driver = null)
    {
        $driver = $driver ?: $this->getDefaultDriver();

        if (is_null($driver)) {
            throw new InvalidArgumentException(sprintf(
                'Unable to resolve NULL driver for [%s].', static::class
            ));
        }

        if (!isset($this->drivers[$driver])) {
            $this->drivers[$driver] = $this->createDriver($driver);
        }

        return $this->drivers[$driver];
    }

    public function getDefaultDriver()
    {
        return $this->app->config['cache.default'];
    }

    protected function createDriver($driver)
    {
        $config = $this->app->config->get("cache.drivers.{$driver}");
        $method = 'create' . Str::studly($config['driver']) . 'Driver';

        if (method_exists($this, $method)) {
            return $this->$method($config);
        }

        throw new InvalidArgumentException("Driver [$driver] not supported.");
    }

    public function createFileDriver($config)
    {
        $config = array_merge([
            'default_ttl' => 0,
            'namespace' => 'yzc',
            'save_path' => '@runtime/cache',
        ], $config);
        return new FilesystemAdapter($config['namespace'], $config['default_ttl'], $this->app->pathAliases->get($config['save_path']));
    }

    public function createRedisDriver($config)
    {
        $config = array_merge([
            'default_ttl' => 0,
            'namespace' => 'yzc',
            'connection' => 'cache',
        ], $config);
        return new RedisAdapter($this->app->get('redis')->driver($config['connection'])->getClient(), $config['namespace'], $config['default_ttl']);
    }
}
