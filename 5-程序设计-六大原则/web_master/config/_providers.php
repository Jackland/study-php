<?php

return [
    \Framework\Session\SessionServiceProvider::class,
    \Framework\Redis\RedisServiceProvider::class,
    \Framework\Cache\CacheServiceProvider::class,
    \Framework\DB\DatabaseServiceProvider::class,
    \Framework\Storage\StorageServiceProvider::class,
    \Framework\View\ViewServiceProvider::class,
    \Framework\Translation\TranslationServiceProvider::class,
    \Framework\Debug\DebugServiceProvider::class,
    \Framework\IdeHelper\IdeHelperServiceProvider::class,
    \Framework\WebpackEncore\WebpackEncoreServiceProvider::class,

    \App\Providers\LockServiceProvider::class,
    \App\Providers\ValidationServiceProvider::class,
    \App\Providers\SmsServiceProvider::class,
    \App\Providers\DBEncryptServiceProvider::class,
    \App\Providers\BarcodeServiceProvider::class,
    \App\Providers\DomPdfServiceProvider::class,
    \App\Providers\MPdfServiceProvider::class,

    \App\Providers\AppServiceProvider::class,
];
