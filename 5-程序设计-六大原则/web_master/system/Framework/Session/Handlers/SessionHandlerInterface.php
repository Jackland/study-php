<?php

namespace Framework\Session\Handlers;

interface SessionHandlerInterface
{
    /**
     * 读取数据
     * @param $sessionId
     * @return array
     */
    public function read($sessionId);

    /**
     * 写入数据
     * @param $sessionId
     * @param $data array
     * @return bool
     */
    public function write($sessionId, $data);

    /**
     * 销毁数据
     * @param $sessionId
     * @return bool
     */
    public function destroy($sessionId);

    /**
     * GC
     * @param int $expire 秒级时间戳
     * @return bool
     */
    public function gc($expire);
}
