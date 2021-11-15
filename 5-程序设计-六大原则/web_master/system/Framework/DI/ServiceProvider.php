<?php

namespace Framework\DI;

use Framework\Foundation\Application;
use Illuminate\Console\Application as Artisan;

abstract class ServiceProvider
{
    /**
     * @var Application
     */
    protected $app;
    /**
     * 延迟加载
     * @var bool
     */
    protected $defer = false;
    /**
     * @var array
     */
    public $bindings = [];
    /**
     * @var array
     */
    public $singletons = [];

    /**
     * @param Application $app
     */
    public function __construct($app)
    {
        $this->app = $app;
    }

    /**
     * 注册服务
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * 启动
     */
    public function boot()
    {
        //
    }

    /**
     * 别名 ['exist' => [Class1, Class2]]
     * @return array
     */
    public function alias()
    {
        return [];
    }

    /**
     * defer 加载时使用
     * @return array
     */
    public function provides()
    {
        $alias = $this->alias();
        return collect([array_keys($alias), $alias])->flatten()->toArray();
    }

    /**
     * Get the events that trigger this service provider to register.
     *
     * @return array
     */
    public function when()
    {
        return [];
    }

    /**
     * @return bool
     */
    public function isDeferred()
    {
        return $this->defer || $this instanceof DeferrableProvider;
    }

    /**
     * 注册命令
     * @param $commands
     */
    public function commands($commands)
    {
        $commands = is_array($commands) ? $commands : func_get_args();

        Artisan::starting(function ($artisan) use ($commands) {
            $artisan->resolveCommands($commands);
        });
    }
}
