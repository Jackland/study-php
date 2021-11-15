<?php

namespace Framework\Exception\Http;

use Framework\Http\Response;
use Throwable;

class NotFoundException extends HttpException
{
    public function __construct(string $message = "", Throwable $previous = null, array $headers = [], ?int $code = 0)
    {
        parent::__construct(Response::HTTP_NOT_FOUND, $message, $previous, $headers, $code);
    }
}
