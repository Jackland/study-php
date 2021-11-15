<?php

namespace Framework\Http\Events;

use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ResponseBeforeSend
{
    /**
     * @var SymfonyResponse
     */
    public $response;

    public function __construct(SymfonyResponse $response)
    {
        $this->response = $response;
    }
}
