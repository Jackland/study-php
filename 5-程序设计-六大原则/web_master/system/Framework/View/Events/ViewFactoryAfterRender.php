<?php

namespace Framework\View\Events;

use Framework\View\ViewFactory;

class ViewFactoryAfterRender
{
    public $viewPath;
    public $data;
    public $view;
    public $result;

    public function __construct(string $viewPath, array $data, ViewFactory $view, string $result)
    {
        $this->viewPath = $viewPath;
        $this->data = $data;
        $this->view = $view;
        $this->result = $result;
    }
}
