<?php

namespace App\Components\Sms\EasySms;

use Overtrue\EasySms\Contracts\PhoneNumberInterface;
use Overtrue\EasySms\PhoneNumber;

/**
 * 阿里云国际号码区号前不用加 00
 * https://github.com/overtrue/easy-sms/issues/307
 */
class AliyunInternationalPhoneNumber extends PhoneNumber
{
    public function __construct(PhoneNumberInterface $phoneNumber)
    {
        parent::__construct($phoneNumber->getNumber(), $phoneNumber->getIDDCode());
    }

    public function getZeroPrefixedNumber()
    {
        return $this->getPrefixedIDDCode('') . $this->number;
    }
}
