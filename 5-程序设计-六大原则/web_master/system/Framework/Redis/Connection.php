<?php

namespace Framework\Redis;

use Framework\Exception\NotSupportException;
use Predis\ClientInterface;

/**
 * @mixin ClientInterface
 * 以下为针对部分api进行的代码提示优化
 * @link https://github.com/predis/predis/issues/512
 * @method int rpush($key, ...$values)
 * @method int sadd($key, ...$members)
 */
class Connection
{
    /**
     * @var ClientInterface
     */
    private $redis;

    public function __construct($redis)
    {
        if (!$redis instanceof ClientInterface) {
            // 仅支持 predis 客户端是因为 phpredis 扩展的 api 和 predis 不一致，比如 sadd 方法，已经大小写问题等
            throw new NotSupportException('当前仅支持 predis 客户端');
        }
        $this->redis = $redis;
    }

    public function getClient()
    {
        return $this->redis;
    }

    public function __call($name, $arguments)
    {
        return $this->redis->{$name}(...$arguments);
    }
}
