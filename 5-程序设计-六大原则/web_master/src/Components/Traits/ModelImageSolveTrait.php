<?php

namespace App\Components\Traits;

trait ModelImageSolveTrait
{
    protected $_imageSolveConfig = [];

    /**
     * @param array $config
     * @return $this
     */
    public function imageSolve($config): self
    {
        $new = clone $this;
        $new->_imageSolveConfig = $config;

        return $new;
    }
}
