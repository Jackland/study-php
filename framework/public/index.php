<?php
/**
 * Created by index.php.
 * User: fuyunnan
 * Date: 2021/10/11
 * Time: 15:35
 */

//调用自动加载文件函数
require  __DIR__.'/../vendor/autoload.php';

//加载启动文件
$app = require_once __DIR__.'/../bootstrap/app.php';


//注册时间和路由
with(new Illuminate\Events\EventServiceProvider($app))->register();
with(new Illuminate\Routing\RoutingServiceProvider($app))->register();


//启动Eloquent ORM模块并进行相关配置
$manager =new \Illuminate\Database\Capsule\Manager();
$manager->addConnection(require '../config/database.php');
$manager->bootEloquent();


//加载视图
$app->instance('config',new \Illuminate\Support\Fluent());
$app['config']['view.compiled'] = "/www/study-php/framework/storage/framework/views";
$app['config']['view.paths'] = ["/www/study-php/framework/resources/views"];

with(new Illuminate\View\ViewServiceProvider($app))->register();
with(new Illuminate\Filesystem\FilesystemServiceProvider($app))->register();



//加载路由
require __DIR__.'/../app/Http/Routes.php';

//实例化请求分发处理程序

$request =Illuminate\Http\Request::createFromGlobals();

$response =$app['router']->dispatch($request);

//返回请求的响应

$response->send();