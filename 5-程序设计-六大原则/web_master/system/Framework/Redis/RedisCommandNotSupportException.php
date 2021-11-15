<?php

namespace Framework\Redis;

use RuntimeException;
use Throwable;

class RedisCommandNotSupportException extends RuntimeException
{
    public function __construct($message = "redis 命令不支持", $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
