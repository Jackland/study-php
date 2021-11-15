<?php

namespace Framework\Widget;

use Framework\Exception\InvalidConfigException;
use Framework\View\Component;

abstract class Widget extends Component
{
    protected static $stack = [];

    public static function begin($config = [])
    {
        $widget = static::widget($config);

        static::$stack[] = $widget;

        return $widget;
    }

    public static function end()
    {
        if (empty(self::$stack)) {
            throw new InvalidConfigException(
                'Unexpected ' . static::class . '::end() call. A matching begin() is not found.'
            );
        }

        /** @var static $widget */
        $widget = array_pop(self::$stack);

        if (get_class($widget) !== static::class) {
            throw new InvalidConfigException('Expecting end() of ' . get_class($widget) . ', found ' . static::class);
        }

        return $widget->render();
    }

    /**
     * @param array $config
     * @return $this
     */
    final public static function widget($config = [])
    {
        return static::register($config);
    }

    /**
     * @inheritDoc
     */
    public function render()
    {
        if (!$this->beforeRun()) {
            return '';
        }

        $result = $this->run();
        return $this->afterRun($result);
    }

    /**
     * @return bool
     */
    protected function beforeRun()
    {
        return true;
    }

    /**
     * @param $result
     * @return string
     */
    protected function afterRun($result)
    {
        return $result;
    }
}
