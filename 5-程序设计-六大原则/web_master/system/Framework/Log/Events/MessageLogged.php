<?php

namespace Framework\Log\Events;

use Psr\Log\LoggerInterface;

class MessageLogged
{
    public $logger;
    public $level;
    public $message;
    public $context;

    public function __construct(LoggerInterface $logger, $level, $message, array $context = [])
    {
        $this->logger = $logger;
        $this->level = $level;
        $this->message = $message;
        $this->context = $context;
    }
}
