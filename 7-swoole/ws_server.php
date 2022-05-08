<?php
/**
 * Created by ws_server.php.
 * User: fuyunnan
 * Date: 2020/11/22
 * Time: 10:31
 */

//创建WebSocket Server对象，监听0.0.0.0:9502端口
$ws = new Swoole\WebSocket\Server('0.0.0.0', 9502);

$ws->set([
//    'enable_static_handler' => true,
//    'document_root' => '/var/www/html/swoole/',
    'task_worker_num' => 2, //异步任务的数
    'worker_num' => 3, //worker  进程数 cpu1~4倍 (默认值：CPU 核数)
    'max_connection' => 10000 //最大连接数
]);

//监听WebSocket连接打开事件
$ws->on('open', function ($ws, $request) {
    echo "连接的fd_id-'.$request->fd\n";
//    var_dump($request->fd, $request->server);
    $ws->push($request->fd, "hello, welcome，我们连接上啦\n");
});

//监听WebSocket消息事件
$ws->on('message', function ($ws, $frame) {
    echo date('H:i:s')."--Message：客户端-我收到你的信息啦，你给我的信息--: {$frame->data}\n";
    $data = [
        'task' => 1,
        'fd' => $frame->fd,
    ];
    $ws->task($data);
    $ws->push($frame->fd, "server:我是服务端 我发给你信息-来自关心");
});

/**
 *异步task  任务
 */
$ws->on('task', function ($http, $task_id, $src_worker_id, $data) {
    print_r($data);
    // 耗时场景 10s
    sleep(10);
    return  date('H:i:s')."on task finish-task-id"; // 告诉worker
});
/**
 *异步结束task  任务
 */
$ws->on('finish', function ($http, $task_id, $data) {
    echo "taskId:{$task_id}\n";
    echo date('H:i:s')."-finish-data-sucess:{$data}\n";
});



//监听WebSocket连接关闭事件
$ws->on('close', function ($ws, $fd) {
    echo "client-{$fd} is closed\n";
});

$ws->start();
