<?php

namespace Framework\Debug\Collector\twig;

class TwigDataNotUse
{
    private $notUsed = [];

    public function addNotUse($viewFile, $keys)
    {
        $this->notUsed[] = ['view' => $viewFile, 'keys' => $keys];
    }

    public function getAllNotUse()
    {
        return $this->notUsed;
    }
}
