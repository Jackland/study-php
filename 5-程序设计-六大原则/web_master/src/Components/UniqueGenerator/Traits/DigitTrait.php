<?php

namespace App\Components\UniqueGenerator\Traits;

trait DigitTrait
{
    protected $digit = 6;

    /**
     * 设置位数
     * @param int $digit
     * @return $this
     */
    public function digit(int $digit): self
    {
        $this->digit = $digit;

        return $this;
    }

    /**
     * @param string|int $str
     * @return string
     */
    protected function padLeftDigit($str): string
    {
        return str_pad((string)$str, $this->digit, '0', STR_PAD_LEFT);
    }
}
