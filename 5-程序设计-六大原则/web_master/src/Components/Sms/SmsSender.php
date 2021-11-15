<?php

namespace App\Components\Sms;

use App\Components\Sms\Exceptions\SmsPhoneInvalidException;
use App\Components\Sms\Exceptions\SmsSendOverRateException;
use App\Components\Sms\Exceptions\SmsTemplateErrorException;
use App\Logging\Logger;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Overtrue\EasySms\Contracts\MessageInterface;
use Overtrue\EasySms\Exceptions\GatewayErrorException;
use Overtrue\EasySms\Exceptions\NoGatewayAvailableException;
use Overtrue\EasySms\Message;
use Overtrue\EasySms\PhoneNumber;
use Throwable;

class SmsSender
{
    /**
     * 按照模板发送短信
     * @param int|string|PhoneNumber $phoneNumber
     * @param string $template SmsTemplate 的 const
     * @param array $data 模版的替换值
     * @param array|string $gateways 指定网关
     * @throws GatewayErrorException
     * @throws SmsSendOverRateException
     * @throws SmsTemplateErrorException
     * @throws SmsPhoneInvalidException
     */
    public static function sendTemplate($phoneNumber, string $template, array $data = [], $gateways = [])
    {
        static::send(static::formatPhoneNumber($phoneNumber), new Message([
            'template' => $template,
            'data' => $data,
            'content' => SmsTemplate::getContentByTemplate($template, $data),
            'gateways' => is_array($gateways) ? $gateways : [$gateways],
        ]));
    }

    /**
     * 按照消息类发送短信
     * @param int|string|PhoneNumber $phoneNumber
     * @param MessageInterface $message
     * @throws GatewayErrorException
     * @throws SmsSendOverRateException
     * @throws SmsTemplateErrorException
     * @throws SmsPhoneInvalidException
     */
    public static function sendMessage($phoneNumber, MessageInterface $message)
    {
        static::send(static::formatPhoneNumber($phoneNumber), $message);
    }

    /**
     * 是否真实发送
     * @return bool
     */
    public static function isRealSend(): bool
    {
        return get_env('SMS_REAL_SEND', false);
    }

    /**
     * 实际发送
     * @param PhoneNumber $phoneNumber
     * @param MessageInterface $message
     * @throws GatewayErrorException
     * @throws SmsPhoneInvalidException
     * @throws SmsSendOverRateException
     * @throws SmsTemplateErrorException
     */
    private static function send(PhoneNumber $phoneNumber, MessageInterface $message)
    {
        $log = [
            'type' => 'send',
            'phone' => $phoneNumber->jsonSerialize(),
            'message' => [
                'gateways' => $message->getGateways(),
                'template' => $message->getTemplate(),
                'content' => $message->getContent(),
                'data' => $message->getData(),
            ]
        ];

        if (!static::isRealSend()) {
            // 模拟发送
            $log['type'] = 'mock';
            Logger::sms($log);
            return;
        }
        if (!get_env('SMS_CAN_SEND_EVERYONE', false)) {
            // 校验手机号发送白名单
            if (!in_array($phoneNumber->getNumber(), explode(',', configDB('sms_can_send_white_list', '')))) {
                $log['type'] = 'error not in whitelist';
                Logger::sms($log, 'error');
                throw new InvalidArgumentException("手机号 {$phoneNumber->getNumber()} 不在可发送白名单内");
            }
        }

        try {
            Logger::sms($log);
            $result = app('sms')->send($phoneNumber, $message);
            Logger::sms(['result' => self::formatResult($result)]);
        } catch (NoGatewayAvailableException $e) {
            $result = $e->getResults();
            Logger::sms(['result' => self::formatResult($result)], 'error');

            $exception = $e->getLastException();
            if ($exception instanceof GatewayErrorException) {
                if (Str::containsAll($exception->getMessage(), ['触发', '级流控Permits'])) {
                    // 阿里云：触发小时级流控Permits:5
                    throw new SmsSendOverRateException($exception);
                }
                if (Str::contains($exception->getMessage(), '模板不合法')) {
                    // 阿里云：模板不合法(不存在或被拉黑)
                    throw new SmsTemplateErrorException($exception);
                }
                if (Str::contains($exception->getMessage(), 'invalid mobile number')) {
                    // 阿里云：12345678910invalid mobile number
                    throw new SmsPhoneInvalidException($exception);
                }
                throw $exception;
            }
            throw new GatewayErrorException('no gateway', 500);
        }
    }

    private static function formatPhoneNumber($phoneNumber): PhoneNumber
    {
        if ($phoneNumber instanceof PhoneNumber) {
            return $phoneNumber;
        }
        return new PhoneNumber(trim($phoneNumber), 86);
    }

    private static function formatResult($result): array
    {
        return array_map(function ($item) {
            if (isset($item['exception']) && $item['exception'] instanceof Throwable) {
                $item['exception'] = [
                    'class' => get_class($item['exception']),
                    'message' => $item['exception']->getMessage(),
                ];
            }
            return $item;
        }, $result);
    }
}
