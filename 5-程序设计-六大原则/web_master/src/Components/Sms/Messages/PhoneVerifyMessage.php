<?php

namespace App\Components\Sms\Messages;

use App\Components\Sms\Messages\Traits\GatewayAutoSplitInternationalByPhoneNumber;
use Overtrue\EasySms\Contracts\GatewayInterface;

/**
 * 手机号验证的消息
 * 根据 PhoneNumber 的国家码自动切换 gateway 和 template
 */
class PhoneVerifyMessage extends BaseVerifyCodeMessage
{
    use GatewayAutoSplitInternationalByPhoneNumber;

    /**
     * @inheritDoc
     */
    public function getTemplate(GatewayInterface $gateway = null)
    {
        if (!$gateway) {
            return '';
        }
        return $this->isAliyunInternationalGateway($gateway)
            ? 'SMS_223587028' // Your verification code is ${code}. Use this to finish verification. Please don't share with anyone else.
            : 'SMS_213010071' // 验证码${code}，您正在尝试变更重要信息，请妥善保管账户信息。
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
