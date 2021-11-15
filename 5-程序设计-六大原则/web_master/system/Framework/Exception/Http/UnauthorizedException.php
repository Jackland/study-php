<?php

namespace Framework\Exception\Http;

use Symfony\Component\HttpFoundation\Response;
use Throwable;

class UnauthorizedException extends HttpException
{
    public function __construct(string $message = "", Throwable $previous = null, array $headers = [], ?int $code = 0)
    {
        parent::__construct(Response::HTTP_UNAUTHORIZED, $message, $previous, $headers, $code);
    }
}
