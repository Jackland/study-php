<?php

namespace App\Components\CountDownResolver;

abstract class BaseResolver
{
    protected $sessionBased = true;

    /**
     * 开始倒计时
     * 不校验是否可以开始
     */
    abstract public function start(): void;

    /**
     * 获取倒计时信息
     * @return CountDownInfo
     */
    abstract public function getInfo(): CountDownInfo;

    /**
     * 重置倒计时
     */
    abstract public function reset(): void;

    /**
     * 倒计时是否已结束
     * @return bool
     */
    public function isOver(): bool
    {
        $info = $this->getInfo();
        return $info->getCountDown() <= 0;
    }

    /**
     * 是否基于 session
     * @param bool $enable
     * @return $this
     */
    public function sessionBased(bool $enable): self
    {
        $this->sessionBased = $enable;
        return $this;
    }
}
