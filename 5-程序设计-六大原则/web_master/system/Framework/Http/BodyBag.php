<?php

namespace Framework\Http;

use Framework\Exception\Exception;
use Illuminate\Support\Arr;
use Throwable;

class BodyBag
{
    private $content;

    public function __construct(string $content)
    {
        $this->content = $content;
    }

    /**
     * 原始数据
     * @return string
     */
    public function raw()
    {
        return $this->content;
    }

    /**
     * 转为数组
     * @param bool $throwException 是否在不能转为数组时抛出异常（包括解析异常和解析后非数组内容）
     * @return array
     * @throws Throwable
     */
    public function asArray($throwException = false)
    {
        try {
            $data = json_decode($this->content, true);
            if (!$data || !is_array($data)) {
                throw new Exception('Not valid json data');
            }
            return $data;
        } catch (Throwable $e) {
            if ($throwException) {
                throw $e;
            }
            return [];
        }
    }

    /**
     * 根据 key 获取原始数据中的值，支持 . 操作来获取 json 下的数据
     * @param $key
     * @param null $default
     * @return mixed
     */
    public function get($key, $default = null)
    {
        return Arr::get($this->asArray(), $key, $default);
    }

    /**
     * @param string $key
     * @return bool
     */
    public function has($key)
    {
        return Arr::has($this->asArray(), $key);
    }

    /**
     * @return array
     */
    public function all()
    {
        return $this->asArray();
    }
}
