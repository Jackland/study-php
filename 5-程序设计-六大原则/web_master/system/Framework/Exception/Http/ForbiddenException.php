<?php

namespace Framework\Exception\Http;

use Framework\Http\Response;
use Throwable;

class ForbiddenException extends HttpException
{
    public function __construct(string $message = "", Throwable $previous = null, array $headers = [], ?int $code = 0)
    {
        parent::__construct(Response::HTTP_FORBIDDEN, $message, $previous, $headers, $code);
    }
}
