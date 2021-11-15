<?php

namespace Framework\DataProvider;

class Paginator
{
    private $config = [
        'pageParam' => 'page', // 第几页的参数
        'pageSizeParam' => 'page_limit', // 分页大小的参数
        'defaultPageSize' => 15, // 默认分页大小
        'totalCount' => 0, // 总数
    ];

    private $request;

    public function __construct($config = [])
    {
        $this->config = array_merge($this->config, $config);
        $this->request = request();
    }

    /**
     * 设置总数
     * @param int $totalCount
     */
    public function setTotalCount(int $totalCount)
    {
        $this->config['totalCount'] = $totalCount;
    }

    /**
     * 获取总数
     * @return int
     */
    public function getTotalCount(): int
    {
        return $this->config['totalCount'];
    }

    private $_pageSize;

    /**
     * @param int $pageSize
     */
    public function setPageSize(int $pageSize)
    {
        $this->_pageSize = $pageSize;
    }

    /**
     * 获取分页大小
     * @return int
     */
    public function getPageSize(): int
    {
        if ($this->_pageSize === null) {
            $this->_pageSize = (int)$this->request->query->get($this->config['pageSizeParam'], $this->config['defaultPageSize']);
        }

        return $this->_pageSize;
    }

    /**
     * 获取总页数
     * @return int
     */
    public function getPageCount(): int
    {
        $pageSize = $this->getPageSize();
        $totalCount = $this->getTotalCount();

        if ($pageSize < 1) {
            return $totalCount > 0 ? 1 : 0;
        }

        $totalCount = $totalCount < 0 ? 0 : (int)$totalCount;

        return (int)(($totalCount + $pageSize - 1) / $pageSize);
    }

    private $_page;

    /**
     * 获取当前页
     * @return int 最小为 1
     */
    public function getPage(): int
    {
        if ($this->_page === null) {
            $page = $page = (int)$this->request->query->get($this->config['pageParam'], 1);
            $this->_page = $page < 1 ? 1 : $page;
        }

        return $this->_page;
    }

    /**
     * @param int $page
     */
    public function setPage(int $page)
    {
        $this->_page = $page;
    }

    /**
     * 获取 sql 的 offset 值
     * @return int
     */
    public function getOffset(): int
    {
        $pageSize = $this->getPageSize();

        if ($pageSize < 1) {
            return 0;
        }
        $offset = ($this->getPage() - 1) * $pageSize;
        if ($offset < 1) {
            return 0;
        }

        return $offset;
    }

    /**
     * 获取 sql 的 limit 值
     * @return int
     */
    public function getLimit(): int
    {
        $pageSize = $this->getPageSize();

        return $pageSize < 1 ? 0 : $pageSize;
    }

    /**
     * 分页 url 链接
     * @param int $page
     * @param int|null $pageSize
     * @return string
     */
    public function createUrl(int $page, ?int $pageSize = null): string
    {
        $params = $this->request->query->all();

        if ($pageSize === null || $pageSize <= 0) {
            $pageSize = $this->getPageSize();
        }

        $params[$this->config['pageParam']] = $page;
        $params[$this->config['pageSizeParam']] = $pageSize;

        $route = $this->request->get('route');
        unset($params['route']);
        array_unshift($params, $route);
        return url()->to($params);
    }

    /**
     * 获取没有分页参数的 url
     * @return string
     */
    public function getUrlWithNoPage(): string
    {
        $params = $this->request->query->all();
        unset(
            $params[$this->config['pageParam']],
            $params[$this->config['pageSizeParam']]
        );

        $route = $this->request->get('route');
        unset($params['route']);
        array_unshift($params, $route);
        return url()->to($params);
    }
}
