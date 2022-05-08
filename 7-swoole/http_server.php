<?php
/**
 * Created by http_server.php.
 * User: fuyunnan
 * Date: 2020/11/21
 * Time: 22:42
 */
$http = new Swoole\Http\Server('0.0.0.0', 9501);
$http->set([
//    'enable_static_handler' => true,
//    'document_root' => '/var/www/html/swoole/', //
    'task_worker_num' => 1, //异步任务的数
    'worker_num' => 3, //worker  进程数 cpu1~4倍 (默认值：CPU 核数)
    'max_connection' => 10000 //最大连接数
]);

$http->on('request', function ($request, $response) {
//    var_dump($request->server).'\n';
    $response->header("Content-Type", "text/html; charset=utf-8");
    $response->end("<h1>Hello Swoole. #" . rand(1000, 9999) . "</h1>");
});
$http->on('message', function ($http, $frame) {
    echo "ser-push-message:{$frame->data}\n";
    $http->task([
        'task_id' =>$frame->fd
    ]);
    $http->push($frame->fd, "server-push:".date("Y-m-d H:i:s"));
});

/**
 *异步task  任务
 */
$http->on('task', function ($http, $task_id, $src_worker_id, $data) {
    print_r($data);
    // 耗时场景 10s
    sleep(10);
    return  "on task finish"; // 告诉worker
});
/**
 *异步结束task  任务
 */
$http->on('finish', function ($http, $task_id, $data) {
    echo "taskId:{$task_id}\n";
    echo "finish-data-sucess:{$data}\n";
});

$http->start();