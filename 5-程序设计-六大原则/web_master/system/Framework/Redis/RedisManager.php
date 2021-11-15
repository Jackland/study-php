<?php

namespace Framework\Redis;

use Framework\Foundation\Application;
use InvalidArgumentException;
use Predis\Client;
use Symfony\Component\Cache\Adapter\RedisAdapter;

/**
 * @mixin Connection
 */
class RedisManager
{
    protected $app;

    protected $drivers = [];

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function driver($driver = null): Connection
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
        return $this->app['config']['redis.default'];
    }

    protected function createDriver($driver): Connection
    {
        $options = $this->app->config["redis.connections.{$driver}"];

        if (!$options) {
            throw new InvalidArgumentException("Driver [$driver] not supported.");
        }

        return $this->getRedisClient($options);
    }

    protected function getRedisClient($options): Connection
    {
        /** @see https://symfony.com/doc/4.4/components/cache/adapters/redis_adapter.html */
        $host = $options['host'] ?? '127.0.0.1';
        $port = $options['port'] ?? '6379';
        $password = isset($options['password']) && $options['password'] ? ($options['password'] . '@') : '';
        $database = $options['database'] ?? 0;
        $dns = "redis://{$password}{$host}:{$port}/{$database}";

        $options['class'] = Client::class; // 固定使用 predis
        return new Connection(RedisAdapter::createConnection($dns, $options));
    }

    public function __call($name, $arguments)
    {
        return $this->driver()->{$name}(...$arguments);
    }
}
