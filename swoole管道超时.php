<?php
/**
 * Created by swoole管道超时
 *
 * 需求说明
 * 在限制的特定时间内 如果未执行完 直接终止程序
 * User: fuyunnan
 * Date: 2021/6/16
 * Time: 9:10
 */
while (1) {
    $channel = new \Swoole\Coroutine\Channel(1);
    go(function () use ($channel) {
//                $task = $this->redis->lPop($this->redisKey);
//                if ($task) {
//                    $this->handleTask($task);
//                }
        \Swoole\Coroutine::sleep(4);
        // 执行成功 push
        $channel->push(1);
    });

    // 3s timeout
    $ret = $channel->pop(3);
    if ($ret === false) {
        // todo
        echo "Timeout\r\n";
    }
}