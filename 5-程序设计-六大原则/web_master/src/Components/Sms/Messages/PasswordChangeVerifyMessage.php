<?php

namespace App\Components\Sms\Messages;

use Overtrue\EasySms\Contracts\GatewayInterface;

/**
 * 修改密码的手机号验证的消息
 * 根据 PhoneNumber 的国家码自动切换 gateway 和 template
 */
class PasswordChangeVerifyMessage extends BaseVerifyCodeMessage
{
    /**
     * @inheritDoc
     */
    public function getTemplate(GatewayInterface $gateway = null)
    {
        if (!$gateway) {
            return '';
        }
        return $this->isAliyunInternationalGateway($gateway)
            ? 'SMS_223562152' // You are resetting your password. The verification code is ${code}. Please don't share with anyone.
            : 'SMS_223177205' // 验证码${code}，您正在修改登录密码，请勿泄露！
            ;
    }

    /**
     * @inheritDoc
     */
    public function getData(GatewayInterface $gateway = null)
    {
        return [
            'code' => $this->code,
        ];
    }
}
