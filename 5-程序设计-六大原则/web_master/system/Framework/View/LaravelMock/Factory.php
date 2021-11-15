<?php

namespace Framework\View\LaravelMock;

use Framework\View\ViewFactory;

class Factory implements \Illuminate\Contracts\View\Factory
{
    protected $factory;

    public function __construct(ViewFactory $factory)
    {
        $this->factory = $factory;
    }

    /**
     * @inheritDoc
     */
    public function exists($view)
    {
        return $this->factory->getFinder()->exist($view);
    }

    /**
     * @inheritDoc
     */
    public function file($path, $data = [], $mergeData = [])
    {
        // TODO: Implement file() method.
    }

    /**
     * @inheritDoc
     */
    public function make($view, $data = [], $mergeData = [])
    {
        $data = array_merge($mergeData, $data);

        return new View($this->factory, $view, $data);
    }

    /**
     * @inheritDoc
     */
    public function share($key, $value = null)
    {
        // TODO: Implement share() method.
    }

    /**
     * @inheritDoc
     */
    public function composer($views, $callback)
    {
        // TODO: Implement composer() method.
    }

    /**
     * @inheritDoc
     */
    public function creator($views, $callback)
    {
        // TODO: Implement creator() method.
    }

    /**
     * @inheritDoc
     */
    public function addNamespace($namespace, $hints)
    {
        // TODO: Implement addNamespace() method.
    }

    /**
     * @inheritDoc
     */
    public function replaceNamespace($namespace, $hints)
    {
        // TODO: Implement replaceNamespace() method.
    }
}
