<?php

namespace Framework\View;

class Util
{
    /**
     * 构建路径
     * @param mixed ...$paths
     * @return string
     */
    public static function buildPath(...$paths)
    {
        $startWithSeparator = isset($paths[0][0]) && $paths[0][0] === '/';
        return ($startWithSeparator ? '/' : '') . implode('/', array_map(function ($path) {
                return ltrim(str_replace('\\', '/', $path), '/');
            }, $paths));
    }

    /**
     * 是否是 url 路径
     * @param $path
     * @return bool
     */
    public static function isHttpUrl($path)
    {
        return strpos($path, '//') === 0 || strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0;
    }
}
