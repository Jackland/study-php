<?php

namespace Framework\Exception;

class InvalidArgumentException extends Exception
{
    /**
     * @return string
     */
    public function getName()
    {
        return 'Invalid Argument';
    }
}
