<?php

namespace App\Traits;

/**
 * 用于命令行模式输出
 *
 * Trait CommandLoggerTrait
 * @package App\Traits
 */
trait CommandLoggerTrait
{
    /**
     * 输出log
     *
     * @param $msg
     * @param string $type info|error|comment|question|line
     */
    private function logger($msg, string $type = 'info')
    {
        if (!in_array($type, ['info', 'error', 'comment', 'question', 'line'])) {
            return;
        }
        $msg = is_array($msg) ? json_encode($msg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $msg;
        $this->$type(date('Y-m-d H:i:s') . ': ' . $msg);
    }
}