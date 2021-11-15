<?php

/**
 * Class Registry
 * @package        OpenCart
 * @author        Daniel Kerr
 * @copyright    Copyright (c) 2005 - 2017, OpenCart, Ltd. (https://www.opencart.com/)
 * @license        https://opensource.org/licenses/GPL-3.0
 * @link        https://www.opencart.com
 */
final class Registry
{
    private $data = [];
    private $delayData = [];

    /**
     * @param $key
     * @return mixed|null
     */
    public function get($key)
    {
        // 先从 registry 中获取，以确保先在 App 中注册的对象，后续又在 controller 里注册，导致取到的仍是原对象的 bug
        if (isset($this->data[$key])) {
            return $this->data[$key];
        }
        if (isset($this->delayData[$key])) {
            // 延迟加载
            list($class, $params, $afterInitCallback) = $this->delayData[$key];
            $this->data[$key] = app()->make($class, $params);
            if ($afterInitCallback) {
                call_user_func($afterInitCallback, $this->data[$key]);
            }
            return $this->data[$key];
        }
        if (app()->has($key)) {
            $this->data[$key] = app()->get($key); // 获取一次之后缓存一次
            return $this->data[$key];
        }
        // 获取不到应该报错，但是因为以前的业务逻辑情况，取不到允许为 null
        //dd($key, $this->data);
        return null;
    }

    /**
     * @param string $key
     * @param mixed $value
     */
    public function set($key, $value)
    {
        $this->data[$key] = $value;
    }

    /**
     * 延迟加载
     * @param $key
     * @param $class
     * @param array $params
     * @param null|callable $afterInitCallback
     */
    public function setDelay($key, $class, $params = [], $afterInitCallback = null)
    {
        $this->delayData[$key] = [$class, $params, $afterInitCallback];
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public function has($key)
    {
        return isset($this->data[$key]);
    }
}
