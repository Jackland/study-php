<?php

use App\Components\Sms\EasySms\AliyunInternationalGateway;
use App\Components\Sms\SmsGatewayEnum;
use Overtrue\EasySms\Gateways\AliyunGateway;
use Overtrue\EasySms\Strategies\OrderStrategy;

return [
    // HTTP 请求的超时时间（秒）
    'timeout' => 5.0,

    // 默认发送配置
    'default' => [
        // 网关调用策略，默认：顺序调用
        'strategy' => OrderStrategy::class,

        // 默认的发送网关
        'gateways' => [
            SmsGatewayEnum::ALIYUN,
        ],
    ],
    // 可用的网关配置
    'gateways' => [
        SmsGatewayEnum::ALIYUN => [
            'access_key_id' => get_env('SMS_ALI_AK'),
            'access_key_secret' => get_env('SMS_ALI_SK'),
            'sign_name' => get_env('SMS_ALI_SIGN', '大健云仓'),
            'special_gateway_name' => SmsGatewayEnum::ALIYUN, // 通过该参数判断阿里云国际与国内区别
        ],
        SmsGatewayEnum::ALIYUN_INTERNATIONAL => [
            'access_key_id' => get_env('SMS_ALI_AK'),
            'access_key_secret' => get_env('SMS_ALI_SK'),
            'sign_name' => get_env('SMS_ALI_INTERNATIONAL_SIGN', 'GIGAB2B'),
            'special_gateway_name' => SmsGatewayEnum::ALIYUN_INTERNATIONAL, // 通过该参数判断阿里云国际与国内区别
        ],
    ],

    // 扩展的网关
    'extend_gateways' => [
        SmsGatewayEnum::ALIYUN => AliyunGateway::class,
        SmsGatewayEnum::ALIYUN_INTERNATIONAL => AliyunInternationalGateway::class,
    ],
];
