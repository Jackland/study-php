<?php

return [
    'default' => get_env('CACHE_ADAPTER', 'file'),
    'drivers' => [
        'file' => [
            'driver' => 'file',
            'save_path' => '@runtime/cache',
            'default_ttl' => 3600,
            'namespace' => 'yzc',
        ],
        'redis' => [
            'driver' => 'redis',
            'connection' => 'cache',
            'default_ttl' => 3600,
            'namespace' => 'yzc',
        ],
    ],
];
