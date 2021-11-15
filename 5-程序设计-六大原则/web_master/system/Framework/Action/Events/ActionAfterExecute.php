<?php

namespace Framework\Action\Events;

use Framework\Action\Action;

class ActionAfterExecute
{
    public $action;
    public $result;
    public $exception;

    public function __construct(Action $action)
    {
        $this->action = $action;
    }
}
