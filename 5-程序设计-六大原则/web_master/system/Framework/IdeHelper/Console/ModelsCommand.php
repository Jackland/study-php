<?php

namespace Framework\IdeHelper\Console;

class ModelsCommand extends \Barryvdh\LaravelIdeHelper\Console\ModelsCommand
{
    public function option($key = null)
    {
        if ($key === 'ignore') {
            // v2.6.6 以下版本不支持配置 ignored_models，做兼容
            $ignoredModels = $this->getLaravel()['config']->get('ide-helper.ignored_models');
            $ignore = parent::option($key);
            if ($ignore) {
                $ignoredModels[] = $ignore;
            }
            return implode(',', $ignoredModels);
        }

        return parent::option($key);
    }
}
