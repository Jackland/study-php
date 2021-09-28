<?php
/**
 * Created by receive.php.
 *
 * 自定义一个简单的消费者
 *
 *
 * User: fuyunnan
 * Date: 2021/9/27
 * Time: 16:20
 */

use PhpAmqpLib\Connection\AMQPStreamConnection;

require_once __DIR__ . '/vendor/autoload.php';

$connection = new AMQPStreamConnection('rabbitmq', 5672, 'root', 'root');
$channel = $connection->channel();

//$channel->queue_declare('hello', false, false, false, false);

echo " [*] Waiting for messages. To exit press CTRL+C\n";

$callback = function ($msg) {
    echo ' [x] Received ', $msg->body, "\n";
};

$channel->basic_consume('hello_test', '', false, true, false, false, $callback);

while ($channel->is_open()) {
    $channel->wait();
}

//关闭连接和通道
$channel->close();
$connection->close();