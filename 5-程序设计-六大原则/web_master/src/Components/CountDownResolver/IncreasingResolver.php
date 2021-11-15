<?php

namespace App\Components\CountDownResolver;

class IncreasingResolver extends BaseResolver
{
    private $key;
    /**
     * 规则
     * @var array
     * [
     * 小于等于N次 => 秒级倒计时
     * 5 => 60,
     * 10 => 300,
     * 9999 => 3600,
     * ]
     */
    private $rules;

    private $cache;

    public function __construct(string $key, array $rules)
    {
        $this->key = $key;
        $this->rules = $rules;

        $this->cache = cache();
    }

    /**
     * @inheritDoc
     */
    public function start(): void
    {
        $count = 0;
        if ($cache = $this->cache->get($this->getCacheKey())) {
            $count = $cache['count'];
        }
        $countDown = $this->getRuleByCount($count);

        $this->cache->set($this->getCacheKey(), [
            'count' => $count + 1, // 已经开始的次数
            'overTime' => time() + $countDown
        ], 24 * 3600);
    }

    /**
     * @inheritDoc
     */
    public function getInfo(): CountDownInfo
    {
        if ($cache = $this->cache->get($this->getCacheKey())) {
            $left = $cache['overTime'] - time();
            if ($left > 0) {
                return new CountDownInfo($left);
            }
        }

        return new CountDownInfo(0);
    }

    /**
     * @inheritDoc
     */
    public function reset(): void
    {
        $this->cache->delete($this->getCacheKey());
    }

    /**
     * 根据已处理次数获取规则
     * @param int $count
     * @return int
     */
    private function getRuleByCount(int $count): int
    {
        foreach ($this->rules as $maxCount => $rule) {
            if ($count < $maxCount) {
                return $rule;
            }
        }
        return 0;
    }

    /**
     * 缓存的 key
     * @return array
     */
    private function getCacheKey(): array
    {
        return [__CLASS__, $this->sessionBased ? session()->getId() : '', $this->key, 'v1'];
    }
}
