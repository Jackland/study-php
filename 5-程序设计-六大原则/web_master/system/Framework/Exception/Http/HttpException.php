<?php

namespace Framework\Exception\Http;

use Framework\Exception\Exception;
use Framework\Http\Response;
use Throwable;

class HttpException extends Exception
{
    private $statusCode;
    private $headers;

    public function __construct(int $statusCode, string $message = '', Throwable $previous = null, array $headers = [], ?int $code = 0)
    {
        $this->statusCode = $statusCode;
        $this->headers = $headers;

        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode()
    {
        return $this->statusCode;
    }

    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * @param array $headers Response headers
     */
    public function setHeaders(array $headers)
    {
        $this->headers = $headers;
    }

    /**
     * @return string
     */
    public function getName()
    {
        if (isset(Response::$statusTexts[$this->statusCode])) {
            return Response::$statusTexts[$this->statusCode];
        }

        return 'Error';
    }
}
