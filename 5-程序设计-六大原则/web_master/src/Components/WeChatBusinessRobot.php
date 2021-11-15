<?php

namespace App\Components;

use App\Logging\Logger;
use Framework\Exception\InvalidConfigException;
use Symfony\Component\HttpClient\HttpClient;

// 企业微信机器人
class WeChatBusinessRobot
{
    protected $client;

    protected $url;
    protected $msgType;
    protected $params = [];

    public function __construct($webHookKey)
    {
        if (strpos($webHookKey, 'https://') !== 0) {
            $this->url = 'https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key=' . $webHookKey;
        } else {
            $this->url = $webHookKey;
        }
        $this->client = HttpClient::create();
    }

    public function text($text)
    {
        $this->msgType = 'text';
        $this->params['text']['content'] = $text;

        return $this;
    }

    public function markdown($content)
    {
        $this->msgType = 'markdown';
        $this->params['markdown']['content'] = $content;

        return $this;
    }

    public function at($mobile)
    {
        if (isset($this->params[$this->msgType]['mentioned_mobile_list'])) {
            $this->params[$this->msgType]['mentioned_mobile_list'][] = $mobile;
        }

        return $this;
    }

    public function send()
    {
        if (!$this->msgType) {
            throw new InvalidConfigException('未知的 msgType');
        }

        $this->params['msgtype'] = $this->msgType;
        try {
            $response = $this->client->request('POST', $this->url, [
                'json' => $this->params,
            ]);
            $data = $response->toArray(false);
            Logger::weChatBusinessRobot(['url' => $this->url, 'params' => $this->params, 'response' => $data]);
        } catch (\Throwable $e) {
            Logger::weChatBusinessRobot(['url' => $this->url, 'params' => $this->params, $e->getMessage()], 'error');
            $data = [];
        }
        return $data;
    }
}
