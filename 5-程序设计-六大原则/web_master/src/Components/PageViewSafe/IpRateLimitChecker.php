<?php

namespace App\Components\PageViewSafe;

use App\Logging\LogChannel;
use Framework\Helper\StringHelper;

/**
 * Ip 访问频率控制
 */
class IpRateLimitChecker extends BaseChecker
{
    const MODE_ALL = 'all'; // 针对符合条件的所有路由
    const MODE_ROUTE = 'route'; // 仅针对当前路由, 该值为默认

    protected $logChannel = LogChannel::SAFE_CHECKER_IP_RATE_LIMIT;
    protected $cacheKeyPrefix = 'safeCheckerIpRateLimit.';
    /**
     * @var array
     */
    protected $rules = [];
    /**
     * 规则的例子
     * @var array
     */
    private $___rule_example___ = [
        // 规则说明：
        // key 为 匹配路由，可以是 product/* 的形式，匹配方式见 Support::matchWildcard()
        // 值为二维数组，表示意思，每[time]分钟访问超过[limit]次数之后报警，mode 见上方 const::MODE_ 说明
        'product/product' => [
            ['time' => 1, 'limit' => 30],
            ['time' => 60, 'limit' => 400],
            ['time' => 180, 'limit' => 600],
        ],
        'product/*' => [
            ['time' => 1, 'limit' => 40],
            ['time' => 60, 'limit' => 500],
            ['time' => 180, 'limit' => 800],
        ],
        '*' => [
            ['time' => 1, 'limit' => 200, 'mode' => self::MODE_ALL],
        ],
    ];

    /**
     * @inheritDoc
     */
    protected function doCheck()
    {
        $currentRoute = $this->request->get('route', 'common/home');
        $ip = $this->request->getUserIp();

        foreach ($this->rules as $pattern => $routeRules) {
            if (!StringHelper::matchWildcard($pattern, $currentRoute)) {
                continue;
            }
            foreach ($routeRules as $rule) {
                if (!isset($rule['mode'])) {
                    $rule['mode'] = static::MODE_ROUTE;
                }
                $cacheKey = $this->getIncrementCacheKey($ip, $rule, $currentRoute);
                $currentCount = $this->cache->increment($cacheKey, 1, $rule['time'] * 60);
                if ($currentCount <= $rule['limit']) {
                    // 总访问次数未达标的
                    continue;
                }

                $this->writeTriggerLog([
                    'count' => $currentCount,
                    'pattern' => $pattern,
                    'rule' => $rule,
                ]);
                return [$ip, $rule, $currentRoute];
            }
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function pass($data)
    {
        if (count($data) !== 3) {
            return;
        }
        list($ip, $rule, $currentRoute) = $data;
        if (!isset($rule['mode'])) {
            return;
        }
        $cacheKey = $this->getIncrementCacheKey($ip, $rule, $currentRoute);
        if (!$this->cache->has($cacheKey)) {
            return;
        }
        // 清除当前规则的累加值
        $this->cache->delete($cacheKey);
        // 当规则是针对单个路由的，需要清除所有针对所有路由的
        if ($rule['mode'] == static::MODE_ROUTE) {
            foreach ($this->rules as $rule) {
                if (isset($rule['mode']) && $rule['mode'] === static::MODE_ALL) {
                    $cacheKey = $this->getIncrementCacheKey($ip, $rule, $currentRoute);
                    $this->cache->delete($cacheKey);
                }
            }
        }
    }

    private function getIncrementCacheKey(string $ip, array $rule, $currentRoute = '')
    {
        $cacheKey = array_merge([$ip], $rule);
        if ($rule['mode'] === static::MODE_ROUTE) {
            $cacheKey[] = $currentRoute;
        }
        return $cacheKey;
    }
}
