<?php

namespace Framework\Exception;

class NotSupportException extends Exception
{
    /**
     * @return string
     */
    public function getName()
    {
        return 'Not Support';
    }
}
