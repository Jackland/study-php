<?php

require_once __DIR__ . '/vendor/autoload.php';
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
/**
 * Created by publish.php.  分为消息发布者（publisher.php)
 * User: fuyunnan
 * Date: 2021/8/27
 * Time: 14:10
 */
$connection = new AMQPStreamConnection('rabbitmq', 5672, 'root', 'root','/');
$channel = $connection->channel();

//创建一个队列（不存在即创建，存在不创建）
//$channel->queue_declare('hello_test', false, false, false, false);
$msg = new AMQPMessage('Hello World!'.rand(1,1000));
$channel->basic_publish($msg, '', 'hello_test');
echo " [x] Sent 'Hello World!'\n";

$channel->close();
$connection->close();
