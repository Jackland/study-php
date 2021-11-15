#!/usr/bin/env php
<?php

if (!in_array(PHP_SAPI, ['cli', 'phpdbg', 'embed'], true)) {
    echo 'Warning: The console should be invoked via the CLI version of PHP, not the ' . PHP_SAPI . ' SAPI' . PHP_EOL;
}

set_time_limit(0);

$_SERVER['HTTP_HOST'] = ''; // 兼容在 env.php 中会使用到 $_SERVER['HTTP_HOST'] 变量导致报 undefined index 错误的情况
require __DIR__ . '/config.php';

defined('OC_DEBUG') or define('OC_DEBUG', false);
defined('OC_ENV') or define('OC_ENV', 'prod');

require_once(DIR_SYSTEM . 'startup.php');

/** @var \Framework\Foundation\Application $app */
$app = require_once __DIR__ . '/config/bootstrap.php';

$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);

$status = $kernel->handle(
    $input = new Symfony\Component\Console\Input\ArgvInput,
    new Symfony\Component\Console\Output\ConsoleOutput
);

$kernel->terminate($input, $status);

exit($status);
