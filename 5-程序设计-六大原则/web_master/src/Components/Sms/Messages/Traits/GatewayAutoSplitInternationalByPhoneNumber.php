<?php

namespace App\Components\Sms\Messages\Traits;

use App\Components\Sms\SmsGatewayEnum;
use InvalidArgumentException;
use Overtrue\EasySms\Contracts\GatewayInterface;
use Overtrue\EasySms\Gateways\Gateway;
use Overtrue\EasySms\PhoneNumber;

trait GatewayAutoSplitInternationalByPhoneNumber
{
    /**
     * @var string|PhoneNumber
     */
    protected $phoneNumber;

    /**
     * @inheritDoc
     */
    public function getGateways()
    {
        if (!$this->phoneNumber instanceof PhoneNumber) {
            $this->phoneNumber = new PhoneNumber($this->phoneNumber, 86);
        }

        return $this->phoneNumber->getIDDCode() == 86
            ? [SmsGatewayEnum::ALIYUN]
            : [SmsGatewayEnum::ALIYUN_INTERNATIONAL];
    }

    /**
     * 判断是否是阿里云国际网关
     * @param GatewayInterface $gateway
     * @return bool
     */
    protected function isAliyunInternationalGateway(GatewayInterface $gateway): bool
    {
        if (!$gateway instanceof Gateway) {
            throw new InvalidArgumentException('gateway 必须是一个 Overtrue\EasySms\Gateways\Gateway 实例');
        }
        return $gateway->getConfig()->get('special_gateway_name') === SmsGatewayEnum::ALIYUN_INTERNATIONAL;
    }
}
