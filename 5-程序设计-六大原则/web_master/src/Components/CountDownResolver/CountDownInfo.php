<?php

namespace App\Components\CountDownResolver;

use Illuminate\Contracts\Support\Arrayable;

class CountDownInfo implements Arrayable
{
    const UNIT_SECOND = 's';
    const UNIT_MINUTE = 'm';

    private $unitChangeLimit = 300;
    private $countDown;

    private $info = [
        'countDown' => 0,
        'countDownUnit' => self::UNIT_SECOND,
        'countDownInterval' => 1000,
    ];

    public function __construct(int $countDown)
    {
        $this->countDown = $countDown;
    }

    /**
     * 达到 $second 秒后的倒计时切换为分钟单位
     * @param int $second
     * @return $this
     */
    public function unitChangeLimit(int $second): self
    {
        $this->unitChangeLimit = $second;
        return $this;
    }

    public function getCountDown(): int
    {
        $this->solveUnit();
        return $this->info['countDown'];
    }

    public function getUnit(): string
    {
        $this->solveUnit();
        return $this->info['countDownUnit'];
    }

    public function getInterval(): int
    {
        $this->solveUnit();
        return $this->info['countDownInterval'];
    }

    /**
     * @inheritDoc
     */
    public function toArray()
    {
        $this->solveUnit();
        return $this->info;
    }

    private $_solved = [];

    private function solveUnit()
    {
        if (isset($this->_solved[$this->unitChangeLimit])) {
            return;
        }
        if ($this->unitChangeLimit <= 60 || $this->countDown <= $this->unitChangeLimit) {
            // 小于 60 秒的不切换 | 倒计时没达到切换标准的
            $this->info['countDown'] = $this->countDown;
            return;
        }
        // 需要显示分钟
        $this->info['countDown'] = intval(ceil($this->countDown / 60)); // 分钟值
        $this->info['countDownUnit'] = self::UNIT_MINUTE;
        $this->info['countDownInterval'] = 60000;
    }
}
