<?php

namespace App\Components\Sms\Exceptions;

use Overtrue\EasySms\Exceptions\GatewayErrorException;

/**
 * 手机号不合法异常
 */
class SmsPhoneInvalidException extends GatewayErrorException
{
    public function __construct(GatewayErrorException $e)
    {
        parent::__construct($e->getMessage(), $e->getCode(), $e->raw);
    }
}
