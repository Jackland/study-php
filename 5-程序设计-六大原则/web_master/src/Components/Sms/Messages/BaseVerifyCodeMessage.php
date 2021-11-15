<?php

namespace App\Components\Sms\Messages;

use App\Components\Sms\Messages\Traits\GatewayAutoSplitInternationalByPhoneNumber;
use Overtrue\EasySms\Message;

abstract class BaseVerifyCodeMessage extends Message
{
    use GatewayAutoSplitInternationalByPhoneNumber;

    protected $code;

    public function __construct($phoneNumber, string $code)
    {
        parent::__construct();

        $this->phoneNumber = $phoneNumber;
        $this->code = $code;
    }
}
