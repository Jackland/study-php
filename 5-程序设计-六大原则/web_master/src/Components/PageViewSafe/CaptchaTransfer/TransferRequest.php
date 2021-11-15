<?php

namespace App\Components\PageViewSafe\CaptchaTransfer;

use App\Components\PageViewSafe\BaseChecker;
use Psr\Log\LoggerInterface;

class TransferRequest implements TransferInterface
{
    public $ts;
    public $checker;
    public $data;
    public $backUrl;

    public function __construct(?BaseChecker $checker, $data, $config = [])
    {
        if ($checker) {
            $this->checker = basename(get_class($checker));
        }
        $this->data = $data;

        foreach ($config as $k => $v) {
            $this->{$k} = $v;
        }

        if (!$this->ts) {
            $this->ts = time();
        }
        if (!$this->backUrl) {
            $this->backUrl = url()->current(true);
        }
    }

    public function getData(): array
    {
        return [
            'ts' => $this->ts,
            'checker' => $this->checker,
            'backUrl' => $this->backUrl,
            'data' => $this->data,
        ];
    }

    /**
     * @param LoggerInterface $logger
     * @return bool|string bool 或 redirect
     */
    public function checkDataVerified(LoggerInterface $logger)
    {
        if (abs($this->ts - time()) > 300) {
            $logger->warning('data ts 超时');
            // ts 超时时重新返回原页面，应该会刷新ts后再次进入验证码页面
            return 'redirect';
        }

        return true;
    }

    public static function loadFromData(array $data): ?TransferInterface
    {
        if (!isset($data['backUrl']) || !isset($data['data'])) {
            return null;
        }
        return new self(null, null, $data);
    }
}
