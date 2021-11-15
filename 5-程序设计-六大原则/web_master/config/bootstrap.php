<?php
/**
 * @var $application_config
 */

use App\Logging\LogChannel;
use Framework\Foundation\Application;

$appConfig = require __DIR__ . '/app.php';
$ocConfig = $application_config ?? null;

$app = new Application($appConfig, $ocConfig);

// http
$app->singleton(\Framework\Foundation\Http\Kernel::class);
// console
$app->singleton(
    \Illuminate\Contracts\Console\Kernel::class,
    \App\Commands\Kernel::class
);
// Exception
$app->singleton(
    \Framework\Contracts\Debug\ExceptionHandler::class,
    function () {
        return new \App\Components\Debug\ExceptionHandler(logger(LogChannel::ERROR));
    }
);

return $app;
