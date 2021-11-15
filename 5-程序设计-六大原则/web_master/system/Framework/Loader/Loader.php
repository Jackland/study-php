<?php

namespace Framework\Loader;

use Exception;
use Framework\Loader\Events\LoadControllerAfter;
use Framework\Loader\Events\LoadControllerBefore;
use Framework\Loader\Events\LoadViewAfter;
use Framework\Loader\Events\LoadViewBefore;
use Throwable;

final class Loader extends \Loader
{
    /**
     * @inheritDoc
     */
    public function controller($route, $data = [])
    {
        event(new LoadControllerBefore($route, $data));

        $afterExecute = new LoadControllerAfter($route, $data);

        try {
            $afterExecute->result = parent::controller($route, $data);
        } catch (Throwable $e) {
            $afterExecute->exception = $e;
        }

        event($afterExecute);

        if ($afterExecute->exception) {
            throw $afterExecute->exception;
        }

        return $afterExecute->result;
    }

    /**
     * @inheritDoc
     */
    public function view($route, $data = [], $layout = '')
    {
        event(new LoadViewBefore($route, $data, $layout));

        $afterExecute = new LoadViewAfter($route, $data, $layout);

        try {
            $afterExecute->result = parent::view($route, $data, $layout);
        } catch (Throwable $e) {
            $afterExecute->exception = $e;
        }

        event($afterExecute);

        if ($afterExecute->exception) {
            throw $afterExecute->exception;
        }

        return $afterExecute->result;
    }

    /**
     * load 模型并返回
     * @param string $route
     * @return mixed|void|null
     * @throws Exception
     */
    public function model($route)
    {
        parent::model($route);
        $name = 'model_' . str_replace('/', '_', $route);
        if ($this->registry->has($name)) {
            return $this->registry->get($name);
        }
        return null;
    }

    /**
     * load library 并返回
     * @param string $route
     * @return mixed|void|null
     * @throws Exception
     */
    public function library($route)
    {
        parent::library($route);
        $name = basename($route);
        if ($this->registry->has($name)) {
            return $this->registry->get($name);
        }
        return null;
    }
}
