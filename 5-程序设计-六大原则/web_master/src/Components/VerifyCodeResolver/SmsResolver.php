<?php

namespace App\Components\VerifyCodeResolver;

use App\Components\Sms\Messages\BaseVerifyCodeMessage;
use App\Components\Sms\SmsSender;
use InvalidArgumentException;
use Overtrue\EasySms\PhoneNumber;

class SmsResolver extends BaseResolver
{
    /**
     * @var PhoneNumber
     */
    private $phone;

    private $template;
    private $message;
    private $codeAttribute;

    public function __construct($phone)
    {
        parent::__construct();

        if (!$phone instanceof PhoneNumber) {
            $phone = new PhoneNumber($phone, 86);
        }
        $this->phone = $phone;
    }

    public function setTemplate(string $template, $codeAttribute = 'code'): self
    {
        $this->template = $template;
        $this->codeAttribute = $codeAttribute;
        return $this;
    }

    /**
     * @param string $message BaseVerifyCodeMessage 的 class
     * @return $this
     */
    public function setMessage(string $message): self
    {
        $this->message = $message;
        return $this;
    }

    /**
     * @inheritDoc
     */
    protected function sendInner(string $code): void
    {
        if ($this->template) {
            SmsSender::sendTemplate($this->phone, $this->template, [
                $this->codeAttribute => $code,
            ]);
            return;
        }
        if ($this->message) {
            $messageClass = $this->message;
            /** @var BaseVerifyCodeMessage $message */
            $message = new $messageClass($this->phone, $code);
            SmsSender::sendMessage($this->phone, $message);
            return;
        }
        throw new InvalidArgumentException('请先调用 setTemplate 或 setMessage 设置需要发送消息');
    }

    /**
     * @inheritDoc
     */
    protected function getCacheKey()
    {
        return [__CLASS__, $this->phone->getUniversalNumber(), 'v1'];
    }
}
