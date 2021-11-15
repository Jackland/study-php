<?php

namespace App\Components\PageViewSafe;

use App\Logging\LogChannel;
use Framework\Helper\StringHelper;

/**
 * IP 访问路由时的检查
 * 首次访问匹配路由时检查不通过
 */
class IpRouteChecker extends BaseChecker
{
    protected $logChannel = LogChannel::SAFE_CHECKER_IP_ROUTE;
    protected $cacheKeyPrefix = 'safeCheckerIpRoute.';

    protected $checkRoutes = ['*'];
    protected $cacheTime = 15 * 24 * 3600;

    /**
     * @inheritDoc
     */
    protected function doCheck()
    {
        if ($this->customer->isLogged()) {
            // 已登录不检查
            return true;
        }

        $currentRoute = $this->request->get('route', 'common/home');
        foreach ($this->checkRoutes as $checkRoute) {
            if (!StringHelper::matchWildcard($checkRoute, $currentRoute)) {
                continue;
            }

            $ip = $this->request->getUserIp();
            if (!$this->cache->has($this->getCacheKey($ip))) {
                $this->writeTriggerLog();
                // 未通过的返回ip
                return $ip;
            }

            // 已通过的，更新缓存时间
            $this->pass($ip);
            return true;
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function pass($data)
    {
        $this->cache->set($this->getCacheKey($data), time(), $this->cacheTime);
    }

    private function getCacheKey($ip)
    {
        return [__CLASS__, $ip];
    }
}
