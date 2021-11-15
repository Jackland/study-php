<?php

namespace Framework\Exception\Http;

use Framework\Http\Response;
use Throwable;

class MethodNotAllowedException extends HttpException
{
    public function __construct(string $message = "", Throwable $previous = null, array $headers = [], ?int $code = 0)
    {
        parent::__construct(Response::HTTP_METHOD_NOT_ALLOWED, $message, $previous, $headers, $code);
    }
}
