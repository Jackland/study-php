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


$connection = new AMQPStreamConnection('rabbitmq', 5672, 'root', 'root');
$channel = $connection->channel();

//创建一个交换机
//$e_name = 'test_exchange'; //交换机名
//$ex = new AMQPExchange($channel);
//$ex->setName($e_name);

$channel->queue_declare('hello', false, false, false, false);
$msg = new AMQPMessage('Hello World!');
$channel->basic_publish($msg, '', 'hello');

echo " [x] Sent 'Hello World!'\n";

$channel->close();
$connection->close();


//$conn_args = array(
//    'host' => '127.0.0.1',
//    'port' => '5672',
//    'login' => 'root',
//    'password' => 'root',
//    'vhost' => '/'
//);
//
////创建连接和channel
//$conn = new AMQPConnection($conn_args);
//if (!$conn->connect()) {
//    die("Cannot connect to the broker!\n");
//}
//$channel = new AMQPChannel($conn);
//
////创建交换机
//$e_name = 'test'; //交换机名
//$ex = new AMQPExchange($channel);
//$ex->setName($e_name);
//$ex->setType(AMQP_EX_TYPE_DIRECT); //direct类型
//$ex->setFlags(AMQP_DURABLE); //持久化
//$ex->declareExchange();
//
//$sStr = 'hello world';
//
////路由的key
//$routeKey = 'test_route_one';
////echo "Send Message:".$ex->publish("TEST MESSAGE，key_1 by xust" . date('H:i:s', time()), 'key_1')."\n";
////echo "Send Message:".$ex->publish("TEST MESSAGE，key_2 by xust" . date('H:i:s', time()), 'key_2')."\n";
//$ex->publish($sStr, $routeKey);
//echo "end\n";