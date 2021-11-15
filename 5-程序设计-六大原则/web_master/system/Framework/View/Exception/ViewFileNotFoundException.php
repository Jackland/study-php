<?php

namespace Framework\View\Exception;

use Framework\Exception\Exception;
use Throwable;

class ViewFileNotFoundException extends Exception
{
    private $viewPath;

    public function __construct($viewPath, $message = "", $code = 0, Throwable $previous = null)
    {
        $this->viewPath = $viewPath;

        if (!$message) {
            $message = "The view file does not exist: $viewPath";
        }

        parent::__construct($message, $code, $previous);
    }
}
