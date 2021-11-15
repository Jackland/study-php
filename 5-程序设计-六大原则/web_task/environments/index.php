<?php

$envArr = [
    'dev' => 'dev',
    't1' => 't1',
    't2' => 't2',
    'test' => 'test',
];
$config = [];
foreach ($envArr as $name => $path) {
    $config[$name] = [
        'path' => $path,
    ];
}

return $config;
