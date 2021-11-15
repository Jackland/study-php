<?php

namespace Framework\Cache;

use Doctrine\DBAL\Exception\InvalidFieldNameException;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class Cache implements CacheInterface
{
    protected $adapter;
    protected $logger;
    protected $keyPrefix = '';

    public function __construct(CacheInterface $adapter, LoggerInterface $logger)
    {
        $this->adapter = $adapter;
        $this->logger = $logger;
    }

    /**
     * @param $prefix
     * @return $this
     */
    public function setKeyPrefix($prefix)
    {
        $new = clone $this;
        $new->keyPrefix = $prefix;

        return $new;
    }

    /**
     * @param $key
     * @param $callable
     * @param null $ttl
     * @return mixed
     */
    public function getOrSet($key, $callable, $ttl = null)
    {
        $randomStr = md5(microtime(true));
        if (($value = $this->get($key, $randomStr)) !== $randomStr) {
            return $value;
        }

        $value = call_user_func($callable, $this);
        if (!$this->set($key, $value, $ttl)) {
            $this->logger->warning('Fail to set cache value for key ' . json_encode($key));
        }

        return $value;
    }

    /**
     * @param mixed $key
     * @param int $value
     * @param null|int $ttl 仅在 key 不存在时有效，若存在，则 ttl 不更新
     * @return int
     */
    public function increment($key, $value = 1, $ttl = null)
    {
        $timestamp = time();
        $cacheValue = $this->get($key, false);
        if ($cacheValue === false) {
            $expiredAt = $ttl ? ($timestamp + $ttl) : 0;
            $cacheValue = ['c' => $value, 'ex' => $expiredAt];
        } else {
            $cacheValue['c'] += $value;
            if ($cacheValue['ex'] != 0) {
                $ttl = $cacheValue['ex'] - $timestamp;
            }
        }
        if (!$this->set($key, $cacheValue, $ttl)) {
            $this->logger->warning('Fail to set cache value for key ' . json_encode($key));
        }

        return $cacheValue['c'];
    }

    /**
     * @param mixed $key
     * @param int $value
     * @param null|int $ttl 仅在 key 不存在时有效，若存在，则 ttl 不更新
     * @return int
     */
    public function decrement($key, $value = 1, $ttl = null)
    {
        return $this->increment($key, -$value, $ttl);
    }

    /**
     * 获取自增值的数量
     * @param $key
     * @param null $default
     * @return mixed|null|int
     */
    public function getIncrementCount($key, $default = null)
    {
        $count = $this->get($key, false);
        if ($count === false || !is_array($count) || !isset($count['c']) || !isset($count['ex'])) {
            return $default;
        }
        if ($count['ex'] - time() > 0) {
            return $count['c'];
        }
        return $default;
    }

    /**
     * @param string|array $key
     * @inheritDoc
     */
    public function get($key, $default = null)
    {
        $key = $this->buildKey($key);
        return $this->adapter->get($key, $default);
    }

    /**
     * @param string|array $key
     * @inheritDoc
     */
    public function set($key, $value, $ttl = null)
    {
        $key = $this->buildKey($key);
        return $this->adapter->set($key, $value, $ttl);
    }

    /**
     * @param string|array $key
     * @inheritDoc
     */
    public function delete($key)
    {
        $key = $this->buildKey($key);
        return $this->adapter->delete($key);
    }

    /**
     * @param string|array $key
     * @inheritDoc
     */
    public function clear()
    {
        return $this->adapter->clear();
    }

    /**
     * @param string|array $key
     * @inheritDoc
     */
    public function getMultiple($keys, $default = null)
    {
        foreach ($keys as &$key) {
            $key = $this->buildKey($key);
        }
        return $this->adapter->getMultiple($keys, $default);
    }

    /**
     * @param string|array $key
     * @inheritDoc
     */
    public function setMultiple($values, $ttl = null)
    {
        $newValues = [];
        foreach ($values as $key => $value) {
            $newValues[$this->buildKey($key)] = $value;
        }
        return $this->adapter->setMultiple($newValues, $ttl);
    }

    /**
     * @param string|array $key
     * @inheritDoc
     */
    public function deleteMultiple($keys)
    {
        foreach ($keys as &$key) {
            $key = $this->buildKey($key);
        }
        return $this->adapter->deleteMultiple($keys);
    }

    /**
     * @param string|array $key
     * @inheritDoc
     */
    public function has($key)
    {
        $key = $this->buildKey($key);
        return $this->adapter->has($key);
    }

    /**
     * @param string|array $key
     * @return string
     */
    public function buildKey($key)
    {
        if (is_string($key)) {
            if (mb_strlen($key) > 64 || false !== strpbrk($key, ItemInterface::RESERVED_CHARACTERS)) {
                $key = md5($key);
            }
        } else {
            $key = md5(serialize($key));
        }

        return $this->keyPrefix . $key;
    }
}
