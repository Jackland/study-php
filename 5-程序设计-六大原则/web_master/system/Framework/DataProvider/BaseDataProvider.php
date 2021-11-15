<?php

namespace Framework\DataProvider;

use Framework\Exception\InvalidConfigException;

abstract class BaseDataProvider implements DataProviderInterface
{
    private $sort;
    private $enableSort = true;
    private $paginator;
    private $enablePaginator = true;
    private $totalCount;

    /**
     * @return Sort
     * @throw InvalidConfigException
     */
    public function getSort(): Sort
    {
        if ($this->sort === null) {
            $this->setSort(app(Sort::class));
        }

        return $this->sort;
    }

    /**
     * @param null|Sort|array $sort
     * @throw InvalidConfigException
     */
    public function setSort($sort): void
    {
        if ($sort === null) {
            $this->sort = null;
            return;
        }
        if ($sort instanceof Sort) {
            $this->sort = $sort;
            return;
        }
        if (is_array($sort)) {
            $this->sort = app(Sort::class, ['config' => $sort]);
            return;
        }

        throw new InvalidConfigException();
    }

    /**
     * @return Paginator
     * @throws InvalidConfigException
     */
    public function getPaginator(): Paginator
    {
        if ($this->paginator === null) {
            $this->setPaginator(app(Paginator::class));
        }

        return $this->paginator;
    }

    /**
     * @param null|Paginator|array $paginator
     * @throw InvalidConfigException
     */
    public function setPaginator($paginator): void
    {
        if ($paginator === null) {
            $this->paginator = null;
            return;
        }
        if ($paginator instanceof Paginator) {
            $this->paginator = $paginator;
        } elseif (is_array($paginator)) {
            $this->paginator = app(Paginator::class, ['config' => $paginator]);
        } else {
            throw new InvalidConfigException();
        }

        $this->paginator->setTotalCount($this->getTotalCount());
    }

    /**
     * @return int
     */
    public function getTotalCount(): int
    {
        if ($this->totalCount === null) {
            $this->totalCount = $this->prepareTotalCount();
        }

        return $this->totalCount;
    }

    /**
     * @param bool $enable
     */
    public function switchSort(bool $enable)
    {
        $this->enableSort = $enable;
    }

    /**
     * @param bool $enable
     */
    public function switchPaginator(bool $enable)
    {
        $this->enablePaginator = $enable;
    }

    /**
     * @return bool
     */
    public function isSortEnable(): bool
    {
        return $this->enableSort;
    }

    /**
     * @return bool
     */
    public function isPaginatorEnable(): bool
    {
        return $this->enablePaginator;
    }

    /**
     * @return int
     */
    abstract protected function prepareTotalCount(): int;
}
