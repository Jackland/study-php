<?php
/**
 * Created by index.php.
 * User: fuyunnan
 * Date: 2021/9/27
 * Time: 10:47
 */
require_once 'vendor/autoload.php';

use Acme\view;
use App\name;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

//"Fuyunnan\\Study\\": "src/",

view::getView();
echo PHP_EOL;
name::getName();
echo PHP_EOL;
setTime();
echo PHP_EOL;


$log = new Logger('name');
$log->pushHandler(new StreamHandler('app_ccc.log', Logger::WARNING));
$log->warning('Foo testing');
$log->error('Bar testing bar');
echo 'success';