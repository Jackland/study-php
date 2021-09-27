<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit608b4bb25b4d82437f48efc490d25770
{
    public static $files = array (
        'd09ff398b54c188d184f3aa723fc5270' => __DIR__ . '/../..' . '/component/function.php',
    );

    public static $prefixLengthsPsr4 = array (
        'P' => 
        array (
            'Psr\\Log\\' => 8,
        ),
        'M' => 
        array (
            'Monolog\\' => 8,
        ),
        'A' => 
        array (
            'App\\' => 4,
            'Acme\\' => 5,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Psr\\Log\\' => 
        array (
            0 => __DIR__ . '/..' . '/psr/log/Psr/Log',
        ),
        'Monolog\\' => 
        array (
            0 => __DIR__ . '/..' . '/monolog/monolog/src/Monolog',
        ),
        'App\\' => 
        array (
            0 => __DIR__ . '/../..' . '/app',
        ),
        'Acme\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit608b4bb25b4d82437f48efc490d25770::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit608b4bb25b4d82437f48efc490d25770::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit608b4bb25b4d82437f48efc490d25770::$classMap;

        }, null, ClassLoader::class);
    }
}
