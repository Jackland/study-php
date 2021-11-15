<?php

namespace Framework\Action;

use Framework\Action\Events\ActionAfterExecute;
use Framework\Action\Events\ActionBeforeExecute;
use Framework\Exception\Http\NotFoundException;
use Framework\Exception\InvalidConfigException;
use Throwable;

final class Action
{
    private $id;
    private $route;
    private $method = 'index';

    public function __construct($route)
    {
        $this->id = $route;

        $parts = explode('/', preg_replace('/[^a-zA-Z0-9_\/]/', '', (string)$route));

        // Break apart the route
        while ($parts) {
            $file = DIR_APPLICATION . 'controller/' . implode('/', $parts) . '.php';

            if (is_file($file)) {
                $this->route = implode('/', $parts);

                break;
            } else {
                $this->method = array_pop($parts);
            }
        }
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getRoute()
    {
        return $this->route;
    }

    /**
     * @return string
     */
    public function getMethod()
    {
        return $this->method;
    }

    public function execute($registry, array $args = [])
    {
        event(new ActionBeforeExecute($this));

        $afterExecute = new ActionAfterExecute($this);
        try {
            $afterExecute->result = $this->executeGetResult($registry, $args);
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
     * @param $registry
     * @param array $args
     * @return NotFoundException|InvalidConfigException|mixed|string|null
     * @throws Throwable
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    protected function executeGetResult($registry, array $args = array())
    {
        // Stop any magical methods being called
        if (substr($this->method, 0, 2) == '__') {
            return new InvalidConfigException('Error: Calls to magic methods are not allowed!');
        }

        $file = DIR_APPLICATION . 'controller/' . $this->route . '.php';
        $class = 'Controller' . preg_replace('/[^a-zA-Z0-9]/', '', $this->route);

        // Initialize the class
        if (is_file($file)) {
            include_once(modification($file));

            $controller = app()->make($class, ['registry' => $registry]);
            if (headers_sent()) {
                // 使用 response->redirectTo()->send() 之后需要停止后续的方法调用
                return null;
            }
        } else {
            return new NotFoundException('Error: Could not call ' . $this->route . '/' . $this->method . '!');
        }

        try {
            return app()->call([$controller, $this->method], $args);
        } catch (Throwable $e) {
            throw $e;
        }
    }
}
