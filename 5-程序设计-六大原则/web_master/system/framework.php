<?php
/**
 * @var $application_config
 */

/** @var \Framework\Foundation\Application $app */
$app = require_once __DIR__ . '/../config/bootstrap.php';

$kernel = $app->make(\Framework\Foundation\Http\Kernel::class);
$response = $kernel->handle($request = \Framework\Http\Request::createFromGlobals());
$response->send();
$kernel->terminate($request, $response);
