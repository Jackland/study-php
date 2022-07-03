<?php
/**
 * Created by setting.php.
 * User: fuyunnan
 * Date: 2022/5/27
 * Time: 16:00
 */

namespace Acme\AbstractTest;


trait DebugUsageTrait
{

    private $_debug_memoryUsage = 0;
    private $_debug_timeStart = 0;
    private $_debug_timeUsage = 0;

    protected function isDebugUsageEnable(): bool
    {
        return false;
    }

    protected function debugUsage(?string $mark = null)
    {
        if (!$this->isDebugUsageEnable()) {
            return;
        }

        $memoryUsage = memory_get_usage(true) / 1024 / 1024;
        $memoryIncrease = $memoryUsage - $this->_debug_memoryUsage;

        $microtime = microtime(true);
        if ($this->_debug_timeStart === 0) {
            $this->_debug_timeStart = $microtime;
        }
        $timeUsage = $microtime - $this->_debug_timeStart;
        $timeIncrease = $timeUsage - $this->_debug_timeUsage;

        echo ($mark ? "【{$mark}】" : '') . "Memory: increase: {$memoryIncrease}M, total: {$memoryUsage}M; Time :increase {$timeIncrease}s, total: {$timeUsage}s" . PHP_EOL;

        $this->_debug_memoryUsage = $memoryUsage;
        $this->_debug_timeUsage = $timeUsage;
    }

}