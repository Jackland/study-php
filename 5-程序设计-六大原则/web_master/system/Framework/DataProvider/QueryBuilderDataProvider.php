<?php

namespace Framework\DataProvider;

use Framework\DataProvider\Traits\QueryDataProviderTrait;
use Illuminate\Database\Query\Builder as QueryBuilder;

class QueryBuilderDataProvider extends BaseDataProvider implements DataProviderCursorGetInterface
{
    use QueryDataProviderTrait;

    /**
     * @var QueryBuilder
     */
    private $query;

    public function __construct(QueryBuilder $query)
    {
        $this->query = $query;
    }

    /**
     * @return \Illuminate\Support\Collection|mixed
     */
    public function getList()
    {
        return $this->getListInner()->get();
    }

    /**
     * @inheritDoc
     */
    protected function prepareTotalCount(): int
    {
        return $this->getCountByQuery($this->query);
    }

    /**
     * @inheritDoc
     */
    public function getListWithCursor()
    {
        return $this->getListInner()->cursor();
    }

    /**
     * @return QueryBuilder
     */
    protected function getListInner()
    {
        $this->buildSortAndPagination($this->query);

        return $this->query;
    }
}
