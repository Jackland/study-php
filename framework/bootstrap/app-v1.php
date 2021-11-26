<?php
/**
 * Created by app.php.
 * User: fuyunnan
 * Date: 2021/10/11
 * Time: 16:31
 */

//实例化服务器容器,框架的一些功能生成都需要服务容器来实现，服务器容器来来服务注册和解析，比如可以实现下面两个服务的

//注册。注册之后可以用$app['router']来调用服务。

$app=new Illuminate\Container\Container;

//setInstance将服务容器的实例添加为静态属性，这样就可以在任何位置获得服务器的实例。

$app = Illuminate\Container\Container::setInstance($app);


//$app->singleton(
//    Illuminate\Contracts\Http\Kernel::class,
//    App\Http\Kernel::class
//);
//
//$app->singleton(
//    Illuminate\Contracts\Console\Kernel::class,
//    App\Console\Kernel::class
//);
//
//$app->singleton(
//    Illuminate\Contracts\Debug\ExceptionHandler::class,
//    App\Exceptions\Handler::class
//);


return $app;