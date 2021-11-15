<?php

namespace App\Components\Sms;

use MyCLabs\Enum\Enum;

class SmsGatewayEnum extends Enum
{
    const ALIYUN = 'aliyun'; // 阿里云国内
    const ALIYUN_INTERNATIONAL = 'aliyun_international'; // 阿里云国际
}
