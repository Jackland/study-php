<?php

namespace Framework\Exception;

class InvalidConfigException extends Exception
{
    /**
     * @return string
     */
    public function getName()
    {
        return 'Invalid Config';
    }
}
