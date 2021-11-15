<?php

namespace Framework\View\Traits;

/**
 * 视图共享数据相关功能
 */
trait ViewSharedTrait
{
    private $sharedData = [];

    /**
     * 设置全局共享的参数
     * @param array|string $key
     * @param null $value
     */
    public function share($key, $value = null)
    {
        $keys = is_array($key) ? $key : [$key => $value];

        foreach ($keys as $key => $value) {
            $this->sharedData[$key] = $value;
        }
    }

    /**
     * 获取单个或全部的全局共享参数
     * @param null $key
     * @param null $default
     * @return array|mixed|null
     */
    public function getShared($key = null, $default = null)
    {
        if ($key !== null) {
            return $this->sharedData[$key] ?? $default;
        }
        return $this->sharedData;
    }
}
