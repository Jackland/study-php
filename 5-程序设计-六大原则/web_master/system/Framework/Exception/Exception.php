<?php

namespace Framework\Exception;

class Exception extends \Exception
{
    /**
     * @return string
     */
    public function getName()
    {
        return 'Exception';
    }
}
