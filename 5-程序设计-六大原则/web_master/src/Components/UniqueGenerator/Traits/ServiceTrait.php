<?php

namespace App\Components\UniqueGenerator\Traits;

use InvalidArgumentException;

trait ServiceTrait
{
    protected $service;

    /**
     * 设置业务
     * @param string $service
     * @return $this
     */
    public function service(string $service): self
    {
        $this->service = $service;

        return $this;
    }

    /**
     * 检查业务必须设置
     * @throws InvalidArgumentException
     */
    protected function checkServiceMust()
    {
        if (!$this->service) {
            throw new InvalidArgumentException('必须设置 service');
        }
    }
}
