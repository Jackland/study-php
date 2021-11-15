<?php

namespace App\Components\PageViewSafe;

use App\Logging\LogChannel;
use Cart\Customer;
use Framework\Cache\Cache;
use Framework\Helper\StringHelper;
use Framework\Http\Request;
use Framework\Log\LogManager;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

abstract class BaseChecker
{
    /**
     * @var Customer
     */
    protected $customer;
    /**
     * @var Request
     */
    protected $request;
    /**
     * @var Cache
     */
    protected $cache;
    /**
     * cache 缓存的 prefix
     * @var string
     */
    protected $cacheKeyPrefix = 'checker.';
    /**
     * @var LogManager|LoggerInterface
     */
    protected $logger;
    /**
     * 日志的记录位置
     * @var string
     */
    protected $logChannel = LogChannel::SAFE_CHECKER;
    /**
     * 启用开关
     * @var bool
     */
    protected $enable = false;
    /**
     * 触发后跳验证码页面
     * @var bool
     */
    protected $goCaptchaWhenTrigger = true;
    /**
     * 当 $goCaptchaWhenTrigger 为 true 时，这些 ip 不跳
     * @see isNeedGoCaptcha
     * @var array
     */
    protected $goCaptchaWhiteListIps = [];
    /**
     * 当 $goCaptchaWhenTrigger 为 false 时，这些 ip 任然会跳
     * @see isNeedGoCaptcha
     * @var array
     */
    protected $goCaptchaBlackListIps = [];

    public function __construct(Customer $customer, Request $request, Cache $cache, LoggerInterface $logger = null, $config = [])
    {
        $this->customer = $customer;
        $this->request = $request;
        $this->cache = $cache;
        if ($logger === null) {
            $logger = new NullLogger();
        }
        $this->logger = $logger;
        foreach ($config as $k => $v) {
            $this->{$k} = $v;
        }

        if ($this->cacheKeyPrefix) {
            $this->cache = $this->cache->setKeyPrefix($this->cacheKeyPrefix);
        }
        if ($this->logChannel && $this->logger instanceof LogManager) {
            $this->logger = $this->logger->channel($this->logChannel);
        }
    }

    /**
     * 检查，返回 false 时表示不通过，需要跳验证码页面
     * @return bool
     */
    public function check(): bool
    {
        if (!$this->enable) {
            // 未启用直接通过
            return true;
        }

        $result = $this->doCheck();
        if ($result !== true) {
            if ($this->isNeedGoCaptcha()) {
                $this->setForbiddenData($result);
                return false;
            }
        }

        return true;
    }

    /**
     * 执行检查，通过时返回 true，否则返回后续用于 pass 处理的数据
     * @return true|mixed
     */
    abstract protected function doCheck();

    /**
     * 在触发规则时是否跳验证码
     * @return bool
     */
    protected function isNeedGoCaptcha(): bool
    {
        $ip = $this->request->getUserIp();
        if ($this->goCaptchaWhenTrigger) {
            foreach ($this->goCaptchaWhiteListIps as $pattern) {
                if (StringHelper::matchWildcard($pattern, $ip)) {
                    return false;
                }
            }
            return true;
        }

        foreach ($this->goCaptchaBlackListIps as $pattern) {
            if (StringHelper::matchWildcard($pattern, $ip)) {
                return true;
            }
        }
        return false;
    }

    private $_forbiddenData;

    /**
     * 检查未通过时调用该方法设置
     * @param mixed $data
     */
    public function setForbiddenData($data)
    {
        $this->_forbiddenData = $data;
    }

    /**
     * 当前检查未通过时的数据，用于返回给 pass 使用
     * @return mixed
     */
    public function getForbiddenData()
    {
        return $this->_forbiddenData;
    }

    /**
     * 通过检查
     * @param mixed $data 数据需是 doCheck() 未验证通过时的值
     */
    abstract public function pass($data);

    /**
     * 写入触发后规则后的日志
     * @param mixed $msg
     * @param string $level
     */
    protected function writeTriggerLog($msg = '', $level = 'warning')
    {
        $this->logger->log($level, json_encode([
            'checker' => basename(static::class),
            'msg' => $msg,
            'ua' => $this->request->getUserAgent(),
            'visit' => url()->current(),
            'referer' => $this->request->getReferer(),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
