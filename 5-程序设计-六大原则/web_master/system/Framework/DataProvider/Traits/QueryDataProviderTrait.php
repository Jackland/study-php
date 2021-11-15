<?php

namespace Framework\DataProvider\Traits;

use Framework\DataProvider\Paginator;
use Framework\DataProvider\Sort;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\Expression;

trait QueryDataProviderTrait
{
    /**
     * @param QueryBuilder $query
     */
    protected function buildSortAndPagination(QueryBuilder $query)
    {
        if ($this->isSortEnable()) {
            /** @var Sort $sort */
            $sort = $this->getSort();
            foreach ($sort->getCurrentSortWithAttribute() as $item) {
                $query->orderBy($item['attribute'], $item['direction']);
            }
        }

        if ($this->isPaginatorEnable()) {
            /** @var Paginator $sort */
            $paginator = $this->getPaginator();
            $query->offset($paginator->getOffset())->limit($paginator->getLimit());
        }
    }

    /**
     * @param QueryBuilder $query
     * @return int
     */
    protected function getCountByQuery(QueryBuilder $query)
    {
        $query->offset = null;
        $query->limit = null;

        if ($query->groups) {
            // 存在 group by 的时候使用 select count(*) from (SQL) a 的形式获取总数
            return db()->table(new Expression('(' . $query->toSql() . ') as a'))
                ->addBinding($query->getBindings())
                ->count();
        }
        return $query->count();
    }
}
