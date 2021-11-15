<?php

namespace Framework\Foundation\Traits;

trait ConsoleTrait
{
    public function isConsole()
    {
        return PHP_SAPI === 'cli';
    }
}
