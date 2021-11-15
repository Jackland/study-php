<?php

namespace Framework\Console\Traits;

use Symfony\Component\Console\Input\InputInterface;

trait CommandInputTrait
{
    /**
     * @var InputInterface
     */
    protected $input;

    protected function option($name)
    {
        return $this->input->getOption($name);
    }

    protected function options(array $names)
    {
        $result = [];
        foreach ($names as $name) {
            $result[] = $this->option($name);
        }
        return $result;
    }

    protected function argument($name)
    {
        return $this->input->getArgument($name);
    }

    protected function arguments(array $names)
    {
        $result = [];
        foreach ($names as $name) {
            $result[] = $this->argument($name);
        }
        return $result;
    }
}
