<?php

namespace App\Components\CountDownResolver;

class SimpleResolver extends BaseResolver
{
    private $key;
    private $countDown;

    private $cache;

    public function __construct(string $key, int $countDown)
    {
        $this->key = $key;
        $this->countDown = $countDown;

        $this->cache = cache();
    }

    /**
     * @inheritDoc
     */
    public function start(): void
    {
        $overTime = time() + $this->countDown;
        $this->cache->set($this->getCacheKey(), $overTime, $this->countDown);
    }

    /**
     * @inheritDoc
     */
    public function getInfo(): CountDownInfo
    {
        if ($overTime = $this->cache->get($this->getCacheKey())) {
            $left = $overTime - time();
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
     * 缓存的 key
     * @return array
     */
    private function getCacheKey(): array
    {
        return [__CLASS__, $this->sessionBased ? session()->getId() : '', $this->key, 'v1'];
    }
}
