<?php

namespace Framework;

use Framework\Cache\Cache;
use Framework\Config\Config;
use Framework\DB\DB;
use Framework\Event\Event;
use Framework\Exception\InvalidConfigException;
use Framework\Foundation\Application;
use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Loader\Loader;
use Framework\Route\Url;
use Framework\Session\Session;
use Illuminate\Database\Capsule\Manager;
use Registry;

/**
 * @method static Manager orm()
 * @method static Config config()
 * @method static Session session()
 * @method static Request request()
 * @method static Response response()
 * @method static Registry registry()
 * @method static Url url();
 * @method static Event event();
 * @method static Loader load();
 * @method static DB db();
 * @method static Cache cache();
 *
 * @deprecated 使用小写的方法名代替使用
 */
class App
{
    public function __call($name, $arguments)
    {
        return Application::getInstance()->{$name}(...$arguments);
    }

    public static function __callStatic($name, $arguments)
    {
        $app = Application::getInstance();
        if ($name === 'registry') {
            return $app->ocRegistry;
        }
        return $app->ocRegistry->get($name);
    }

    /**
     * @param $config
     * @param array $params
     * @return mixed|object
     */
    public static function create($config, $params = [])
    {
        $abstract = $config;
        if (is_array($config)) {
            if (!isset($config['__class'])) {
                throw new InvalidConfigException('if config is array, it must contain `__class` key');
            }
            $abstract = $config['__class'];
            unset($config['__class']);
        } else {
            $config = $params;
        }
        return Application::getInstance()->make($abstract, ['config' => $config]);
    }

    /**
     * @param $abstract
     * @return mixed|object
     * @deprecated 不再使用，使用 app()->get($abstract) 代替
     */
    public static function getComponent($abstract)
    {
        return Application::getInstance()->get($abstract);
    }

    /**
     * 绑定参数变量
     * @param $object
     * @param $properties
     * @return mixed
     */
    public static function configure($object, $properties)
    {
        foreach ($properties as $name => $value) {
            $object->$name = $value;
        }

        return $object;
    }
}
