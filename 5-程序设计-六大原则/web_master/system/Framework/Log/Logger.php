<?php

namespace Framework\Log;

use Framework\Log\Events\MessageLogged;
use Illuminate\Contracts\Events\Dispatcher;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;

class Logger implements LoggerInterface
{
    use LoggerTrait;

    protected $logger;
    protected $dispatcher;

    public function __construct(LoggerInterface $logger, Dispatcher $dispatcher = null)
    {
        $this->logger = $logger;
        $this->dispatcher = $dispatcher;
    }

    /**
     * @inheritDoc
     */
    public function log($level, $message, array $context = array())
    {
        if ($this->dispatcher) {
            $this->dispatcher->dispatch(new MessageLogged($this->logger, $level, $message, $context));
        }

        $this->logger->log($level, $message, $context);
    }
}
