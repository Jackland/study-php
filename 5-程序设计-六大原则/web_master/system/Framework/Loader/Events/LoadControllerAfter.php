<?php

namespace Framework\Loader\Events;

use Throwable;

class LoadControllerAfter
{
    public $route;
    public $data;
    public $result;
    /**
     * @var Throwable|null
     */
    public $exception;

    public function __construct($route, $data)
    {
        $this->route = $route;
        $this->data = $data;
    }
}
