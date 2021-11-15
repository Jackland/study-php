<?php

namespace Framework\View\Events;

use Framework\View\ViewFactory;

class ViewFactoryBeforeRender
{
    public $viewPath;
    public $data;
    public $view;

    public function __construct(string $viewPath, array $data, ViewFactory $view)
    {
        $this->viewPath = $viewPath;
        $this->data = $data;
        $this->view = $view;
    }
}
