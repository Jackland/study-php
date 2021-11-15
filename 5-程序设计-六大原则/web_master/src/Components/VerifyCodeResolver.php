<?php

namespace App\Components;

use App\Components\VerifyCodeResolver\EmailResolver;
use App\Components\VerifyCodeResolver\SmsResolver;
use Overtrue\EasySms\PhoneNumber;

class VerifyCodeResolver
{
    /**
     * 短信
     * @param string|PhoneNumber $phone
     * @return SmsResolver
     */
    public static function sms($phone): SmsResolver
    {
        return new SmsResolver($phone);
    }

    /**
     * email
     * @param string $email
     * @return EmailResolver
     */
    public static function email(string $email): EmailResolver
    {
        return new EmailResolver($email);
    }
}
