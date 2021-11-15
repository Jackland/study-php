<?php

namespace App\Components\Sms\EasySms;

use Overtrue\EasySms\Contracts\MessageInterface;
use Overtrue\EasySms\Contracts\PhoneNumberInterface;
use Overtrue\EasySms\Gateways\AliyunGateway;
use Overtrue\EasySms\Support\Config;

/**
 * 阿里云国际号码区号前不用加 00
 * https://github.com/overtrue/easy-sms/issues/307
 */
class AliyunInternationalGateway extends AliyunGateway
{
    public function send(PhoneNumberInterface $to, MessageInterface $message, Config $config)
    {
        $to = new AliyunInternationalPhoneNumber($to);
        return parent::send($to, $message, $config);
    }
}
