<?php

namespace App\Components\Sms;

class SmsTemplate
{
    // 阿里云短信 模版CODE
    const CHANGE_PHONE = 'SMS_213010071'; // 验证码${code}，您正在尝试变更重要信息，请妥善保管账户信息。

    // 其他平台

    /**
     * 根据模版和替换值获取 content
     * 用于其他非模版形式的平台时
     * @param string $template
     * @param array $data
     * @return string|null
     */
    public static function getContentByTemplate(string $template, array $data): ?string
    {
        $map = [
            // 模版 CODE => 实际内容（使用 {code} 的形式定义变量）
            //self::CHANGE_PHONE => '验证码{code}，您正在尝试变更重要信息，请妥善保管账户信息。'
        ];

        if (isset($map[$template])) {
            return strtr($map[$template], $data);
        }
        return null;
    }
}
