<?php

namespace Framework\View\LaravelMock;

use Framework\View\ViewFactory;

class View implements \Illuminate\Contracts\View\View
{
    protected $factory;
    protected $view;
    protected $data;

    public function __construct(ViewFactory $factory, $view, $data = [])
    {
        $this->factory = $factory;
        $this->view = $view;
        $this->data = $data;
    }

    /**
     * @inheritDoc
     */
    public function render()
    {
        return $this->factory->render($this->view, $this->data);
    }

    /**
     * @inheritDoc
     */
    public function name()
    {
        // TODO: Implement name() method.
    }

    /**
     * @inheritDoc
     */
    public function with($key, $value = null)
    {
        if (is_array($key)) {
            $this->data = array_merge($this->data, $key);
        } else {
            $this->data[$key] = $value;
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getData()
    {
        // TODO: Implement getData() method.
    }
}
