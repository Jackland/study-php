<?php

namespace App\Logging\Handlers;

use App\Components\WeChatBusinessRobot;
use Monolog\Handler\AbstractHandler;
use Monolog\Logger;

// 企业微信机器人报警日志
class WeChatBusinessRobotHandler extends AbstractHandler
{
    private $weChatBusinessRobotKey;

    protected $_robot = false;

    public function __construct($weChatBusinessRobotKey, $level = Logger::DEBUG, $bubble = true)
    {
        $this->weChatBusinessRobotKey = $weChatBusinessRobotKey;
        parent::__construct($level, $bubble);
    }

    /**
     * @inheritDoc
     */
    public function handle(array $record)
    {
        if (!$this->weChatBusinessRobotKey) {
            return;
        }

        if ($this->_robot === false) {
            $this->_robot = new WeChatBusinessRobot($this->weChatBusinessRobotKey);
        }

        /**
         * [context][title] 必定存在
         * @see \App\Logging\Logger::alarm()
         */
        $this->_robot->markdown(implode("\n", [
            '<font color="warning">' . $record['context']['title'] . '：</font>',
            '> ' . str_replace('||', "\n", $record['message']),
        ]))->send();
    }
}
