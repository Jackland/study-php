<?php

namespace Framework\DataProvider;

use Framework\DataProvider\Traits\QueryDataProviderTrait;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

class QueryDataProvider extends BaseDataProvider implements DataProviderCursorGetInterface
{
    use QueryDataProviderTrait;

    /**
     * @var EloquentBuilder
     */
    private $query;

    public function __construct(EloquentBuilder $query)
    {
        $this->query = $query;
    }

    /**
     * @return EloquentBuilder[]|\Illuminate\Database\Eloquent\Collection|mixed
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
        return $this->getCountByQuery($this->query->getQuery());
    }

    /**
     * @inheritDoc
     */
    public function getListWithCursor()
    {
        return $this->getListInner()->cursor();
    }

    /**
     * @return EloquentBuilder
     */
    protected function getListInner()
    {
        $this->buildSortAndPagination($this->query->getQuery());

        return $this->query;
    }
}
