<?php

namespace App\Components\Sms\Exceptions;

use Overtrue\EasySms\Exceptions\GatewayErrorException;

/**
 * 短信发送超过频率异常
 */
class SmsSendOverRateException extends GatewayErrorException
{
    public function __construct(GatewayErrorException $e)
    {
        parent::__construct($e->getMessage(), $e->getCode(), $e->raw);
    }
}
