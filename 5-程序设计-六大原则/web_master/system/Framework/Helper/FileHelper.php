<?php

namespace Framework\Helper;

use Illuminate\Filesystem\Filesystem;

class FileHelper
{
    protected static $fileSystem;

    public static function getFilesystem(): Filesystem
    {
        if (self::$fileSystem === null) {
            self::$fileSystem = new Filesystem();
        }
        return self::$fileSystem;
    }

    /**
     * 新建文件夹
     * 若已经存在，则跳过
     *
     * @param string $path
     * @param int $mode
     * @param bool $recursive
     * @return bool
     */
    public static function createDirectory(string $path, int $mode = 0775, $recursive = true): bool
    {
        $fileSystem = static::getFilesystem();
        $path = static::normalizePath($path);

        if ($fileSystem->isDirectory($path)) {
            return true;
        }

        return $fileSystem->makeDirectory($path, $mode, $recursive);
    }

    /**
     * 格式化文件或文件夹路径
     *
     * - 路径分割符改为 / (e.g. "\a/b\c" -> "/a/b/c")
     * - 移除最后的 / (e.g. "/a/b/c/" -> "/a/b/c")
     * - 移除多个分割符 (e.g. "/a///b/c" becomes "/a/b/c")
     * - 修改 .. 和 . 到真实路径 (e.g. "/a/./b/../c" becomes "/a/c")
     *
     * @param string $path
     * @return string
     */
    public static function normalizePath(string $path): string
    {
        $isWindowsShare = strpos($path, '\\\\') === 0;

        if ($isWindowsShare) {
            $path = substr($path, 2);
        }

        $path = rtrim(strtr($path, '/\\', '//'), '/');

        if (strpos('/' . $path, '/.') === false && strpos($path, '//') === false) {
            return $isWindowsShare ? "\\\\$path" : $path;
        }

        $parts = [];

        foreach (explode('/', $path) as $part) {
            if ($part === '..' && !empty($parts) && end($parts) !== '..') {
                array_pop($parts);
            } elseif ($part !== '.' && ($part !== '' || empty($parts))) {
                $parts[] = $part;
            }
        }

        $path = implode('/', $parts);

        if ($isWindowsShare) {
            $path = '\\\\' . $path;
        }

        return $path === '' ? '.' : $path;
    }
}
