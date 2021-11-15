<?php

namespace App\Logging\Traits;

use Psr\Log\LogLevel;

trait AlarmTrait
{
    /**
     * 报警日志
     * 请勿用于告警可能非常频繁的情况
     * @param $title
     * @param array $messages
     * @param string $level
     */
    public static function alarm($title, $messages = [], $level = LogLevel::INFO)
    {
        static::log2alarmChannel(__FUNCTION__, $title, $messages, $level);
    }

    /**
     * @param $channel
     * @param $title
     * @param array $messages
     * @param string $level
     * @see WeChatBusinessRobotHandler::handle()
     */
    protected static function log2alarmChannel($channel, $title, $messages = [], $level = LogLevel::INFO)
    {
        logger($channel)->log($level, is_array($messages) ? implode('||', $messages) : $messages, [
            'title' => $title,
        ]);
    }
}
