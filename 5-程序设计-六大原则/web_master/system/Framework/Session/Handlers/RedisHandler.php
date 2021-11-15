<?php

namespace Framework\Session\Handlers;

use Symfony\Component\HttpFoundation\Session\Storage\Handler\RedisSessionHandler;

class RedisHandler extends RedisSessionHandler implements SessionHandlerInterface
{
    /**
     * @inheritDoc
     * @return array
     */
    public function read($sessionId)
    {
        $data = parent::read($sessionId);
        if (!$data) {
            return [];
        }
        return json_decode($data, true);
    }

    /**
     * @param array $data
     * @inheritDoc
     */
    public function write($sessionId, $data)
    {
        $data = json_encode($data);
        return parent::write($sessionId, $data);
    }
}
