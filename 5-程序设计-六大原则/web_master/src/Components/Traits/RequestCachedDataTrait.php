<?php

namespace App\Components\Traits;

trait RequestCachedDataTrait
{
    private static $_requestCachedData = [];

    /**
     * 获取请求级别的数据缓存
     * @param $key
     * @return null|mixed null 表示缓存未命中
     */
    private function getRequestCachedData($key)
    {
        $key = $this->normalizeCacheKey($key);
        if (isset(self::$_requestCachedData[$key])) {
            return self::$_requestCachedData[$key];
        }
        return null;
    }

    /**
     * 设置请求级别的数据缓存
     * @param $key
     * @param mixed $data 非 null 值，不建议将 null|false|true 设置为缓存数据，可以用 ''|'true'|'false' 代替
     */
    private function setRequestCachedData($key, $data)
    {
        $key = $this->normalizeCacheKey($key);
        self::$_requestCachedData[$key] = $data;
    }

    /**
     * @param mixed $key
     * @param callable $callable
     * @return mixed
     */
    private function requestCachedData($key, callable $callable)
    {
        $data = $this->getRequestCachedData($key);
        if ($data === null) {
            $data = call_user_func($callable);
            $this->setRequestCachedData($key, $data);
        }
        return $data;
    }

    /**
     * @param mixed $key
     * @return string
     */
    private function normalizeCacheKey($key)
    {
        if (!is_string($key)) {
            $key = md5(serialize($key));
        }
        return $key;
    }
}
