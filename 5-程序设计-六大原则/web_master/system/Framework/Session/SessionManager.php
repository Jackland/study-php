<?php

namespace Framework\Session;

use Framework\Foundation\Application;
use Framework\Session\Handlers\DbHandler;
use Framework\Session\Handlers\FileHandler;
use Framework\Session\Handlers\RedisHandler;
use Framework\Session\Handlers\SessionHandlerInterface;
use Illuminate\Support\Str;
use InvalidArgumentException;

class SessionManager
{
    protected $app;

    protected $drivers = [];

    protected $config = [
        'prefix' => 'sess_',
        'ttl' => null,
        'driver' => 'file'
    ];

    public function __construct(Application $app)
    {
        $this->app = $app;

        $this->config = array_merge($this->config, $app->config['session']);
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
        return $this->app['config']['session.driver'];
    }

    protected function createDriver($driver)
    {
        $method = 'create' . Str::studly($driver) . 'Driver';

        if (method_exists($this, $method)) {
            return $this->$method();
        }

        throw new InvalidArgumentException("Driver [$driver] not supported.");
    }

    public function createFileDriver()
    {
        $savePath = $this->app->pathAliases->get($this->app->config['session.file_path']);
        return $this->buildSession(new FileHandler($savePath, [
            'ttl' => $this->config['ttl'],
            'prefix' => $this->config['prefix'],
        ]));
    }

    public function createDbDriver()
    {
        $connection = $this->app->config['session.db_connection'];
        $db = $this->app['db']->connection($connection);
        return $this->buildSession(new DbHandler($db, [
            'ttl' => $this->config['ttl']
        ]));
    }

    public function createRedisDriver()
    {
        $connection = $this->app->config['session.redis_connection'];
        $redis = $this->app->get('redis')->driver($connection)->getClient();
        return $this->buildSession(new RedisHandler($redis, [
            'ttl' => $this->config['ttl'],
            'prefix' => $this->config['prefix'],
        ]));
    }

    protected function buildSession(SessionHandlerInterface $handler)
    {
        return new Session($handler);
    }
}
