<?php

namespace Framework\Loader\Events;

class LoadControllerBefore
{
    public $route;
    public $data;

    public function __construct($route, $data)
    {
        $this->route = $route;
        $this->data = $data;
    }
}
