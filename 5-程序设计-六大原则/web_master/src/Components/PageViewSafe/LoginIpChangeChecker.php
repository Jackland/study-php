<?php

namespace App\Components\PageViewSafe;

use App\Components\PageViewSafe\Traits\GoCaptchaCustomerTrait;
use App\Logging\LogChannel;

/**
 * 已登录用户，Ip 切换后检查
 */
class LoginIpChangeChecker extends BaseChecker
{
    use GoCaptchaCustomerTrait;

    protected $logChannel = LogChannel::SAFE_CHECKER_LOGIN_IP_CHANGE;
    protected $cacheKeyPrefix = 'safeCheckerLoginIpChange.';

    protected $cacheTime = 180;
    /**
     * ip切换容忍数
     * @var array|int[]
     */
    protected $ipCountLimit = [
        'global' => 1, // 针对所有人
        //1 => 2, // 针对 1 这个用户配置数量
    ];

    /**
     * @inheritDoc
     */
    protected function doCheck()
    {
        if (!$this->customer->isLogged()) {
            // 未登录的直接通过
            return true;
        }
        $customerId = $this->customer->getId();
        $sessionId = session()->getId();
        $ip = $this->request->getUserIp();
        if ($this->isIpPass($customerId, $sessionId, $ip)) {
            $this->pass([$customerId, $sessionId, $ip]);
            return true;
        }

        return [$customerId, $sessionId, $ip];
    }

    /**
     * @inheritDoc
     */
    protected function isNeedGoCaptcha(): bool
    {
        return $this->isNeedGoCaptchaContainCustomer($this->customer->getId());
    }

    /**
     * @inheritDoc
     */
    public function pass($data)
    {
        if (count($data) != 3) {
            return;
        }
        list($customerId, $sessionId, $ip) = $data;

        $cacheKey = $this->getCacheKey($customerId, $sessionId);
        $data = $this->cache->get($cacheKey);
        $needUpdate = true;
        if (!$data || !is_array($data)) {
            // 缓存不存在或缓存值异常时，直接记录当前ip
            $data[$ip] = time() + $this->cacheTime;
        } elseif (isset($data[$ip])) {
            // 该 ip 未过期，不做任何处理
            $needUpdate = false;
        } else {
            // ip 不存在
            // 移除过期的ip
            $data = array_filter($data, function ($expireAt) {
                return $expireAt >= time();
            });
            // 保留最新的N个ip
            $keepLimitCount = $this->getIpLimitCount($customerId) - 1;
            if ($keepLimitCount <= 0) {
                $data = [];
            } elseif (count($data) > $keepLimitCount) {
                $data = array_slice($data, -$keepLimitCount);
            }
            // 增加当前ip
            $data[$ip] = time() + $this->cacheTime;
        }

        if ($needUpdate) {
            $this->cache->set($cacheKey, $data, $this->cacheTime);
        }
    }

    private function isIpPass(int $customerId, string $sessionId, string $ip): bool
    {
        $cacheKey = $this->getCacheKey($customerId, $sessionId);
        $data = $this->cache->get($cacheKey);
        if (!$data || !is_array($data)) {
            // 缓存不存在或缓存值异常时，可通过
            return true;
        }
        // 移除过期的ip
        $data = array_filter($data, function ($expireAt) {
            return $expireAt >= time();
        });
        if (isset($data[$ip])) {
            // 该 ip 未过期，可通过
            return true;
        }
        // 是否超过最大使用ip数
        $limitCount = $this->getIpLimitCount($customerId);
        $isOver = count($data) >= $this->getIpLimitCount($customerId);
        $this->writeTriggerLog(['is_over' => $isOver, 'limit' => $limitCount, 'session_id' => $sessionId, 'old_ips' => $data], $isOver ? 'warning' : 'info');
        return !$isOver;
    }

    private function getCacheKey($customerId, $sessionId)
    {
        return [__CLASS__, $customerId, $sessionId, 'v2'];
    }

    private function getIpLimitCount(int $customerId): int
    {
        if (isset($this->ipCountLimit[$customerId])) {
            return $this->ipCountLimit[$customerId];
        }
        return $this->ipCountLimit['global'] ?? 1;
    }
}
