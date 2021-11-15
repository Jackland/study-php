<?php

namespace App\Components\RemoteApi\B2BManager\Exceptions;

use App\Components\RemoteApi\Exceptions\ApiResponseException;
use Throwable;

class NoPermissionException extends ApiResponseException
{
    public function __construct($message = "", $code = 0, Throwable $previous = null)
    {
        $message .= '【请确认是否将本机 ip 加到 tb_b2b_sys_manage_api_ip 表中】';

        parent::__construct($message, $code, $previous);
    }
}
