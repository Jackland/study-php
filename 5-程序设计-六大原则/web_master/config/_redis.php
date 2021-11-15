<?php

return [
    'default' => 'default',
    'connections' => [
        'default' => [
            'host' => get_env('REDIS_HOST', '127.0.0.1'),
            'password' => get_env('REDIS_PASSWORD', ''),
            'port' => get_env('REDIS_PORT', 6379),
            'database' => get_env('REDIS_REDIS_DATABASE', 0),
        ],
        'session' => [
            'host' => get_env('REDIS_HOST', '127.0.0.1'),
            'password' => get_env('REDIS_PASSWORD', ''),
            'port' => get_env('REDIS_PORT', 6379),
            'database' => get_env('REDIS_SESSION_DATABASE', 1),
        ],
        'cache' => [
            'host' => get_env('REDIS_HOST', '127.0.0.1'),
            'password' => get_env('REDIS_PASSWORD', ''),
            'port' => get_env('REDIS_PORT', 6379),
            'database' => get_env('REDIS_CACHE_DATABASE', 2),
        ],
        'b2b_java' => [
            'host' => get_env('REDIS_HOST', '127.0.0.1'),
            'password' => get_env('REDIS_PASSWORD', ''),
            'port' => get_env('REDIS_PORT', 6379),
            'database' => get_env('REDIS_B2B_JAVA_DATABASE', 3),
        ]
    ],
];
