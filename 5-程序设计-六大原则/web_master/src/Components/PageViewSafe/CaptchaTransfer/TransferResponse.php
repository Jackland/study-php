<?php

namespace App\Components\PageViewSafe\CaptchaTransfer;

use Framework\Cache\Cache;
use Illuminate\Support\Str;

class TransferResponse implements TransferInterface
{
    public $ts;
    public $checker;
    public $data;
    public $answer;

    public function __construct($transferRequestData, $config = [])
    {
        if (is_array($transferRequestData)) {
            $this->checker = $transferRequestData['checker'] ?? null;
            $this->data = $transferRequestData['data'] ?? null;
        }

        foreach ($config as $k => $v) {
            $this->{$k} = $v;
        }

        if (!$this->ts) {
            $this->ts = time();
        }
        if (!$this->answer) {
            $this->answer = Str::random();
        }
    }

    public function getData(): array
    {
        return [
            'ts' => $this->ts,
            'checker' => $this->checker,
            'data' => $this->data,
            'answer' => $this->answer,
        ];
    }

    public function checkDataVerified(Cache $cache): bool
    {
        $tsTimeout = 300;
        if (abs($this->ts - time()) > $tsTimeout) {
            // ts 过期
            return false;
        }
        $cacheKey = [__CLASS__, __FUNCTION__, $this->answer, 'v1'];
        if ($cache->has($cacheKey)) {
            // answer 重复使用，防止多次使用相同的回跳url解除限制
            return false;
        }
        $cache->set($cacheKey, 1, $tsTimeout);

        return true;
    }

    public static function loadFromData(array $data): ?TransferInterface
    {
        if (!isset($data['answer'])) {
            return null;
        }
        return new self(null, $data);
    }
}
