<?php

namespace App\Components\UniqueGenerator\Traits;

trait RandomTrait
{
    use DigitTrait;

    protected $firstCanBeZero = true;

    /**
     * 随机数首位不为0
     * @return $this
     */
    public function randomFirstNoZero(): self
    {
        $this->firstCanBeZero = false;

        return $this;
    }

    private $_randomMin = null;
    private $_randomMax = null;

    /**
     * 获取随机数
     * @return int
     */
    protected function getRandomInt(): int
    {
        if ($this->_randomMin === null) {
            $this->_randomMin = $this->firstCanBeZero ? 0 : pow(10, $this->digit - 1);
        }
        if ($this->_randomMax === null) {
            $this->_randomMax = pow(10, $this->digit) - 1;
        }
        return random_int($this->_randomMin, $this->_randomMax);
    }
}
