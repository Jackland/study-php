<?php

namespace Framework\DI;

use Framework\Exception\InvalidArgumentException;
use Illuminate\Support\Str;

trait DependencyLoadTrait
{
    /**
     * 尝试解决 $dependency->getClass() 报错的类
     * @param $dependency
     * @throws InvalidArgumentException
     */
    private static function dependencyTrySolve($dependency)
    {
        // 由于 model 即一些需要手动 load，所以使用此方法自动查找
        $name = $dependency->getName();
        if (substr($name, 0, 5) === 'model') {
            self::dependencyLoadModel($name);
        } else {
            throw new InvalidArgumentException('未找到 class:' . $name . ', 当前仅支持自动 load model');
        }
    }

    /**
     * 依赖使用 load 载入 model 等
     * @param $name
     * @throws InvalidArgumentException
     */
    private static function dependencyLoadModel($name)
    {
        if (substr(DIR_APPLICATION, -6) === 'admin/') {
            $map = config('diMap.model.admin', []);
        } else {
            $map = config('diMap.model.catalog', []);
        }
        if (is_array($map) && isset($map[$name])) {
            $modelPath = $map[$name];
        } else {
            $snake = $name;
            if (strpos($name, '_') === false) {
                // 包含下划线的不进行转化
                $snake = Str::snake($name);
            }

            $arr = explode('_', $snake);
            $path = self::dependencyFindPath(DIR_APPLICATION, $arr);
            if ($path === false) {
                $msg = [
                    'model 的名称写法格式如:',
                    '1. $modelXxxYyy 对应 model/xxx/yyy 或 model/xxx_yyy',
                    '2. $modelXxx_yyy 对应 model/xxx_yyy'
                ];
                throw new InvalidArgumentException('未找到 class:' . $name . ' ' . implode("\n", $msg));
            }
            $relativePath = str_replace([DIR_APPLICATION, '.php'], ['', ''], $path);
            $modelPath = substr(str_replace('\\', '/', $relativePath), 6);
        }

        load()->model($modelPath);
    }

    private static $dependencyFindPathCache = [
        'file' => [],
        'not-file' => [],
    ];

    private static function dependencyFindPath($path, $arr)
    {
        if (!$arr) {
            return false;
        }
        $filename = $path . implode('_', $arr) . '.php';
        if (in_array($filename, self::$dependencyFindPathCache['file'])) {
            return $filename;
        }
        if (!in_array($filename, self::$dependencyFindPathCache['not-file'])) {
            if (is_file($filename)) {
                self::$dependencyFindPathCache['file'][] = $filename;
                return $filename;
            } else {
                self::$dependencyFindPathCache['not-file'][] = $filename;
            }
        }
        return self::dependencyFindPath($path . array_shift($arr) . DIRECTORY_SEPARATOR, $arr);
    }
}
