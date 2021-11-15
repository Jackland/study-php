<?php

namespace Framework\Debug\Traits;

use DebugBar\DataCollector\ExceptionsCollector;
use DebugBar\DataCollector\MessagesCollector;
use Throwable;

trait AddMessageTrait
{
    /**
     * 写入 message
     * @param $message
     * @param string $type
     */
    public function addMessage($message, $type = 'info')
    {
        if (!$this->isEnabled()) {
            return;
        }
        if ($message instanceof Throwable) {
            $this->addException($message);
            $message = $message->getMessage();
            $type = 'error';
        }
        /** @var MessagesCollector $collector */
        $collector = $this->getCollector('messages');
        $collector->addMessage($message, $type);
    }

    /**
     * 添加异常信息
     * @param Throwable $exception
     */
    public function addException(Throwable $exception)
    {
        if (!$this->isEnabled()) {
            return;
        }
        /** @var ExceptionsCollector $collector */
        $collector = $this->getCollector('exceptions');
        $collector->addThrowable($exception);
    }
}
