<?php

namespace Framework\Exception;

class ViewNotFoundException extends Exception
{
    /**
     * @return string
     */
    public function getName()
    {
        return 'View Not Found';
    }
}
