<?php

namespace App\Components\Sms\Exceptions;

use Overtrue\EasySms\Exceptions\GatewayErrorException;

/**
 * 短信模版不合法异常
 */
class SmsTemplateErrorException extends GatewayErrorException
{
    public function __construct(GatewayErrorException $e)
    {
        parent::__construct($e->getMessage(), $e->getCode(), $e->raw);
    }
}
