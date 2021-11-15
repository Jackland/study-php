<?php

namespace Framework\Route;

use Framework\Route\Traits\UrlBuilder;

class Url extends \Url
{
    use UrlBuilder;

    const DEFAULT_REMEMBER_NAME = 'redirect'; // 兼容原代码使用的 session name

    /**
     * @param string|array $url
     * @return string
     */
    public function to($url)
    {
        $route = $url;
        $params = [];
        if (is_array($url)) {
            $route = $url[0];
            unset($url[0]);
            $params = $url;
        }
        if ($route === '') {
            [$route, $queryParams, $hash] = $this->parseRequestUri($this->current());
            $params = array_merge($queryParams, $hash ? ['#' => $hash] : [], $params);
        }
        if (strpos($route, 'javascript') === 0) {
            return $url;
        }
        if (strpos($route, 'index.php?route=') !== false) {
            return $url;
        }
        if (strpos($route, 'http') !== false) {
            return $url;
        }
        if (strpos($route, '/') === 0) {
            return $url;
        }
        return $this->link($route, $params, true);
    }

    /**
     * 记住 url
     * @param $url
     * @param null $name
     */
    public function remember($url = '', $name = null)
    {
        $url = $this->to($url);

        if ($name === null) {
            $name = static::DEFAULT_REMEMBER_NAME;
        }
        session()->set($name, $url);
    }

    /**
     * 上一个记住的 url
     * @param null $name
     * @return mixed
     */
    public function previous($name = null)
    {
        if ($name === null) {
            $name = static::DEFAULT_REMEMBER_NAME;
        }

        return session()->get($name);
    }

    private $_current = null;

    /**
     * 当前路由
     * @param bool $scheme 是否增加域名 http 或 https
     * @return string 形如 /index.php?route=customerpartner/seller_center/recommend/detail&id=2095
     */
    public function current($scheme = false)
    {
        if ($this->_current !== null) {
            return $this->_current;
        }

        // php 不能获取到 url 上的 # 参数
        $url = request()->server('REQUEST_URI');

        if ($scheme !== false) {
            if (!is_string($scheme)) {
                $scheme = request()->getSchemeAndHttpHost();
            }
            $url = $scheme . $url;
        }

        return $this->_current = $url;
    }

    private $_parsedUri = [];

    /**
     * 解析 url
     * @param string $uri 形如 xxx/index.php?route=customerpartner/seller_center/recommend/detail&id=2095#hash
     * @return array ['customerpartner/seller_center/recommend/detail', ['id' => 2095], 'hash']
     */
    public function parseRequestUri(string $uri): array
    {
        if (isset($this->_parsedUri[$uri])) {
            return $this->_parsedUri[$uri];
        }

        $uri = preg_replace('/.*\/index.php\?/i', '', $uri);
        // hash
        $hashArr = explode('#', $uri);
        $hash = '';
        if (count($hashArr) === 2) {
            $hash = $hashArr[1];
            $uri = $hashArr[0];
        }
        // queries
        $queries = explode('&', $uri);
        $params = [];
        foreach ($queries as $param) {
            $item = explode('=', $param);
            $params[$item[0]] = $item[1] ?? '';
        }
        $route = '';
        if (isset($params['route'])) {
            $route = $params['route'];
            unset($params['route']);
        }

        return $this->_parsedUri[$uri] = [$route, $params, $hash];
    }
}
