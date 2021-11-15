<?php

namespace Framework\Foundation\Traits;

use Illuminate\Config\Repository;

trait ConfigTrait
{
    /**
     * @var Repository
     */
    public $config;

    public function loadConfig(array $items)
    {
        $this->instance('config', $this->config = new Repository($items));
        // 设置时区
        date_default_timezone_set($this->config->get('app.date_timezone'));
    }
}
