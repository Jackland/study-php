<?php

namespace Framework\View;

use Framework\View\Traits\ComponentLoadConfig;

abstract class Component
{
    use ComponentLoadConfig;

    /**
     * 加载属性到当前组件
     * @param array $config
     * @return $this
     */
    public function config($config = [])
    {
        $this->loadConfig($config);
        return $this;
    }

    private $_view;

    /**
     * @return ViewFactory
     */
    final public function getView()
    {
        if ($this->_view === null) {
            $this->_view = view();
        }

        return $this->_view;
    }

    /**
     * 快速注册
     * @param array $config
     * @return $this
     */
    public static function register($config = [])
    {
        $widget = app()->make(static::class);
        if ($config) {
            $widget->config($config);
        }

        return $widget;
    }

    /**
     * @return string
     */
    abstract protected function run();

    /**
     * 渲染视图
     * @return string
     */
    public function render()
    {
        return $this->run();
    }

    public function __toString()
    {
        return $this->render();
    }
}
