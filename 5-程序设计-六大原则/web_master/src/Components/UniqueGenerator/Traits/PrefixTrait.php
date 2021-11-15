<?php

namespace App\Components\UniqueGenerator\Traits;

trait PrefixTrait
{
    protected $prefix = '';

    /**
     * 设置前缀
     * @param string $prefix
     * @return $this
     */
    public function prefix(string $prefix): self
    {
        $this->prefix = $prefix;

        return $this;
    }
}
