<?php

namespace Framework\DB;

use Framework\DI\ServiceProvider;
use Framework\Foundation\Application;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Model;

class DatabaseServiceProvider extends ServiceProvider
{
    /**
     * @inheritDoc
     */
    public function register()
    {
        Model::clearBootedModels();

        $this->registerConnectionServices();

        Model::setConnectionResolver($this->app['db.manager']);
        Model::setEventDispatcher($this->app['events']);
    }

    /**
     * @inheritDoc
     */
    public function alias()
    {
        return [
            'db' => [Manager::class],
            'db.manager' => [DatabaseManager::class],
            'db.connection' => [Connection::class, ConnectionInterface::class],
        ];
    }

    protected function registerConnectionServices()
    {
        $this->app->singleton('db', function (Application $app) {
            $manager = new Manager($app);

            foreach ($app->config->get('database.connections', []) as $name => $connection) {
                $manager->addConnection($connection, $name);
            }

            $manager->setAsGlobal();

            return $manager;
        });

        $this->app->singleton('db.manager', function ($app) {
            return $app['db']->getDatabaseManager();
        });

        $this->app->bind('db.connection', function ($app) {
            return $app['db']->getConnection();
        });
    }
}
