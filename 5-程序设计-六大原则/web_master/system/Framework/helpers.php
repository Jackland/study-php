<?php

use Framework\Aliases\Aliases;
use Framework\Debug\DebugBar;
use Framework\Foundation\Application;
use Framework\Log\LogManager;
use Framework\Translation\Translator;
use Framework\View\ViewFactory;

if (!function_exists('app')) {
    /**
     * @param null|string $abstract
     * @param array $params
     * @return Application|mixed
     */
    function app($abstract = null, $params = [])
    {
        $app = Application::getInstance();

        if (is_string($abstract)) {
            return $app->make($abstract, $params);
        }

        return $app;
    }
}

if (!function_exists('aliases')) {
    /**
     * @param null|string $key
     * @return Aliases|string
     */
    function aliases($key = null)
    {
        $aliases = app()->pathAliases;

        if (is_string($key)) {
            return $aliases->get($key);
        }

        return $aliases;
    }
}

if (!function_exists('base_path')) {
    /**
     * @param string $path
     * @return string
     */
    function base_path($path = '')
    {
        return app()->pathAliases->get('@root') . ($path ? DIRECTORY_SEPARATOR . $path : $path);
    }
}

if (!function_exists('cache')) {
    /**
     * @param null|string $key
     * @return \Framework\Cache\Cache|mixed
     */
    function cache($key = null)
    {
        $cache = app()->get('cache');

        if (!is_null($key)) {
            return $cache->get($key);
        }

        return $cache;
    }
}

if (!function_exists('config')) {
    /**
     * 获取项目根目录/config/ 目录下的配置参数
     * @param null|string $key
     * @param null|string $default
     * @return mixed|\Illuminate\Config\Repository
     */
    function config($key = null, $default = null)
    {
        $config = app()->config;

        if (is_string($key)) {
            return $config->get($key, $default);
        }

        return $config;
    }
}

if (!function_exists('configDB')) {
    /**
     * 获取系统 Config 类里设置的值，一般来自于 oc_setting 表
     * @param null|string $key
     * @param null|string $default
     * @return mixed|\Framework\Config\Config
     */
    function configDB($key = null, $default = null)
    {
        $config = app()->ocConfig;

        if (is_string($key)) {
            return $config->get($key) ?: $default;
        }

        return $config;
    }
}

if (!function_exists('db')) {
    /**
     * @param null|string $table
     * @return \Illuminate\Database\Capsule\Manager|\Illuminate\Database\Query\Builder
     */
    function db($table = null)
    {
        $orm = app()->get('db');

        if (!is_null($table)) {
            if (is_a($table, \Illuminate\Database\Eloquent\Model::class, true)) {
                $table = (new $table())->getTable();
            }
            return $orm->table($table);
        }

        return $orm;
    }
}

if (!function_exists('dbTransaction')) {
    /**
     * @param Closure $callback
     * @param int $attempts
     * @param null $connection
     * @return mixed
     * @throws Throwable
     */
    function dbTransaction(Closure $callback, $attempts = 1, $connection = null)
    {
        return db()->getConnection($connection)->transaction($callback, $attempts);
    }
}

if (!function_exists('debugBar')) {
    /**
     * @return DebugBar
     */
    function debugBar()
    {
        return app()->get('debugbar');
    }
}

if (!function_exists('logger')) {
    /**
     * @param null|string $channel
     * @return LogManager
     */
    function logger($channel = null)
    {
        return app()->get('log')->channel($channel);
    }
}

if (!function_exists('registry')) {
    /**
     * @param null|string $key
     * @return Registry|mixed
     */
    function registry($key = null)
    {
        $registry = app()->ocRegistry;

        if (is_string($key)) {
            return $registry->get($key);
        }

        return $registry;
    }
}

if (!function_exists('request')) {
    /**
     * @param null|string $key
     * @param null|mixed $default
     * @return \Framework\Http\Request|mixed
     */
    function request($key = null, $default = null)
    {
        /** @var \Framework\Http\Request $request */
        $request = app()->get('request');

        if (is_string($key)) {
            return $request->attributes->get($key, $default);
        }

        return $request;
    }
}

if (!function_exists('response')) {
    /**
     * @param string $content
     * @param int $status
     * @param array $headers
     * @return \Framework\Http\Response
     */
    function response($content = '', $status = 200, $headers = [])
    {
        /** @var \Framework\Http\Response $response */
        $response = app()->get('response');

        if ($content) {
            $response->setOutput($content);
        }
        $response->setStatusCode($status);
        if ($headers) {
            $response->headers->replace($headers);
        }

        return $response;
    }
}

if (!function_exists('session')) {
    /**
     * @param null|string $key
     * @param null|string $default
     * @return \Framework\Session\Session|mixed
     */
    function session($key = null, $default = null)
    {
        $session = app()->get('session');

        if (is_string($key)) {
            return $session->get($key, $default);
        }

        return $session;
    }
}

if (!function_exists('url')) {
    /**
     * @param null|string|array $to
     * @return \Framework\Route\Url|string
     */
    function url($to = null)
    {
        /** @var \Framework\Route\Url $url */
        $url = app()->ocRegistry->get('url');

        if (is_string($to) || is_array($to)) {
            return $url->to($to);
        }

        return $url;
    }
}

if (!function_exists('load')) {
    /**
     * @return \Framework\Loader\Loader
     */
    function load()
    {
        return app()->ocLoad;
    }
}

if (!function_exists('view')) {
    /**
     * @param null $viewPath
     * @param array $data
     * @param string $layout
     * @return ViewFactory|string
     */
    function view($viewPath = null, $data = [], $layout = '')
    {
        /** @var ViewFactory $factory */
        $factory = app('view');

        if (func_num_args() === 0) {
            return $factory;
        }

        if ($layout) {
            $factory = $factory->withLayout($layout);
        }
        return $factory->render($viewPath, $data);
    }
}

if (!function_exists('event')) {
    /**
     * @param string|object $event
     * @param mixed $payload
     * @param bool $halt
     * @return array|null
     */
    function event($event, $payload = [], $halt = false)
    {
        return app('events')->dispatch($event, $payload, $halt);
    }
}

if (!function_exists('trans')) {
    /**
     * @return Translator
     */
    function trans()
    {
        /** @var Translator $translator */
        $translator = app()->get('translator');

        return $translator;
    }
}
