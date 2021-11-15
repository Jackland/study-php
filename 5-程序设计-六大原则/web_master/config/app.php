<?php

return [
    'aliases' => [
        '@root' => dirname(__DIR__),
        '@runtime' => '@root/runtime',
        '@vendor' => '@root/storage/vendor',
        '@npm' => '@root/storage/npm_assets',
        '@public' => '@root/public',
        '@publicUrl' => '/public',
        // assets
        '@assets' => '@public/assets',
        '@assetsUrl' => '@publicUrl/assets',
        // storage
        '@imageCache' => '@assets',
        '@imageCacheUrl' => '@assetsUrl',
    ],
    'app' => [
        'date_timezone' => 'America/Los_Angeles',
    ],
    'logging' => require __DIR__ . '/_logging.php',
    'storage' => require __DIR__ . '/_storage.php',
    'redis' => require __DIR__ . '/_redis.php',
    'session' => require __DIR__ . '/_session.php',
    'cache' => require __DIR__ . '/_cache.php',
    'debugbar' => require __DIR__ . '/_debugbar.php',
    'database' => require __DIR__ . '/_database.php',
    'translation' => require __DIR__ . '/_translation.php',
    'diMap' => require __DIR__ . '/_di_map.php',
    'events' => require __DIR__ . '/_events.php',
    'providers' => require __DIR__ . '/_providers.php',
    'view' => require __DIR__ . '/_view.php',
    'sms' => require __DIR__ . '/_sms.php',
    'maintain' => require __DIR__ . '/_maintain.php',
    'webpack_encore' => require __DIR__ . '/_webpack_encore.php',
    'controller' => require __DIR__ . '/_controller.php',
];
