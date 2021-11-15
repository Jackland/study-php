<?php

use App\Enums\Common\DatabaseConnection;

return [
    'default' => DatabaseConnection::WRITE, // 固定为 default
    'connections' => [
        DatabaseConnection::WRITE => [
            'driver' => 'mysql',
            'host' => get_env('DB_HOSTNAME', '127.0.0.1'),
            'port' => get_env('DB_PORT', 3306),
            'database' => get_env('DB_DATABASE', 'yzc'),
            'username' => get_env('DB_USERNAME', 'root'),
            'password' => get_env('DB_PASSWORD', 'root'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'modes' => [],
        ],
        DatabaseConnection::READ => [
            'driver' => 'mysql',
            'host' => get_env('DB_READ_HOSTNAME', '127.0.0.1'),
            'port' => get_env('DB_READ_PORT', 3306),
            'database' => get_env('DB_READ_DATABASE', 'yzc'),
            'username' => get_env('DB_READ_USERNAME', 'root'),
            'password' => get_env('DB_READ_PASSWORD', 'root'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'modes' => [],
        ],
    ],
];
