<?php

namespace Framework\Loader\Events;

class LoadViewBefore
{
    public $route;
    public $data;
    public $layout;

    public function __construct($route, $data, $layout)
    {
        $this->route = $route;
        $this->data = $data;
        $this->layout = $layout;
    }
}
