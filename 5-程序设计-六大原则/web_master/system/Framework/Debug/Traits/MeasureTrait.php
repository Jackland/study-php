<?php

namespace Framework\Debug\Traits;

use DebugBar\DataCollector\DataCollectorInterface;
use DebugBar\DataCollector\TimeDataCollector;
use DebugBar\DebugBarException;

trait MeasureTrait
{
    /**
     * 开启时间 trace 记录
     * @param $name
     * @param $label
     */
    public function startMeasure($name, $label = null)
    {
        if (!$this->isEnabled()) {
            return;
        }
        $this->getTimeCollector()->startMeasure($name, $label ?: $name);
    }

    /**
     * 结束 trace 记录
     * 请勿在 footer 之后调用
     * @param $name
     * @throws DebugBarException
     */
    public function stopMeasure($name)
    {
        if (!$this->isEnabled()) {
            return;
        }
        $this->getTimeCollector()->stopMeasure($name);
    }

    /**
     * @param $name
     * @param \Closure $closure
     * @return array|mixed
     */
    public function measure($name, \Closure $closure)
    {
        if (!$this->isEnabled()) {
            return $closure();
        }
        return $this->getTimeCollector()->measure($name, $closure);
    }

    /**
     * @return TimeDataCollector|DataCollectorInterface
     */
    protected function getTimeCollector()
    {
        return $this->getCollector('time');
    }
}
