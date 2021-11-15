<?php

namespace Framework\Exception\Http;

use Framework\Http\Response;
use Throwable;

class BadRequestException extends HttpException
{
    public function __construct(string $message = "", Throwable $previous = null, array $headers = [], ?int $code = 0)
    {
        parent::__construct(Response::HTTP_BAD_REQUEST, $message, $previous, $headers, $code);
    }
}
