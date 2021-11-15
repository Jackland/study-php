<?php

namespace Framework\Route\Traits;

trait UrlBuilder
{
    private $_route = '';
    private $_queries = [];
    private $_hash = '';

    /**
     * 路由名，如：common/home
     * @param string $name
     * @return $this
     */
    public function withRoute(string $name): self
    {
        $this->_route = $name;

        return $this;
    }

    /**
     * 携带 query 参数
     * @param array $queries [key => value]
     * @return $this
     */
    public function withQueries(array $queries): self
    {
        $this->_queries = array_merge($this->_queries, $queries);

        return $this;
    }

    /**
     * 移除 query 参数
     * @param array|string $keys [key1, key2]
     * @return $this
     */
    public function withoutQueries($keys): self
    {
        foreach ((array)$keys as $key) {
            unset($this->_queries[$key]);
        }

        return $this;
    }

    /**
     * 携带 hash 参数，若要置空传递空字符串
     * @param string $hash
     * @return $this
     */
    public function withHash(string $hash): self
    {
        $this->_hash = $hash;

        return $this;
    }

    /**
     * 携带当前路由的参数
     * @return $this
     */
    public function withCurrentQueries(): self
    {
        [,$params, $hash] = $this->parseRequestUri($this->current());
        unset($params['route']);
        $this->withQueries($params);
        $this->withHash($hash);
        return $this;
    }

    /**
     * 构建最终的 url
     * @return array|string
     */
    public function build()
    {
        if (!$this->_route) {
            $this->_route = $this->parseRequestUri($this->current())[0];
        }
        $queries = $this->_queries;
        if ($this->_hash) {
            $queries['#'] = $this->_hash;
        }
        $url = $this->link($this->_route, $queries, true);
        $this->_route = '';
        $this->_queries = [];
        $this->_hash = '';
        return $url;
    }
}
