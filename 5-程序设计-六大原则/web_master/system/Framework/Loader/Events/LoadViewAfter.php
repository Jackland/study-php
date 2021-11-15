<?php

namespace Framework\Loader\Events;

use Throwable;

class LoadViewAfter
{
    public $route;
    public $data;
    public $layout;
    public $result;
    /**
     * @var Throwable|null
     */
    public $exception;

    public function __construct($route, $data, $layout)
    {
        $this->route = $route;
        $this->data = $data;
        $this->layout = $layout;
    }
}
