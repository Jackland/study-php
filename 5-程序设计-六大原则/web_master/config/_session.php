<?php

return [
    'driver' => get_env('SESSION_ADAPTER', 'db'),
    'prefix' => 'sess_',
    'ttl' => get_env('SESSION_TTL', 43200), // 默认12小时
    'file_path' => '@runtime/session',
    'db_connection' => 'default',
    'redis_connection' => 'session',
];
