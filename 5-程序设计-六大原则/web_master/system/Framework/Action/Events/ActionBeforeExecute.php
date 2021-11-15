<?php

namespace Framework\Action\Events;

use Framework\Action\Action;

class ActionBeforeExecute
{
    public $action;

    public function __construct(Action $action)
    {
        $this->action = $action;
    }
}
