<?php

namespace Framework\View\Traits;

trait ComponentLoadConfig
{
    /**
     * @param array $config
     */
    protected function loadConfig($config)
    {
        foreach ($config as $attribute => $value) {
            if (property_exists($this, $attribute)) {
                $this->{$attribute} = $value;
            }
        }
    }
}
