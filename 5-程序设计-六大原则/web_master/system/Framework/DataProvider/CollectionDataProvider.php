<?php

namespace Framework\DataProvider;

use Framework\Exception\NotSupportException;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Collection;

class CollectionDataProvider extends BaseDataProvider
{
    private $collection;

    public function __construct(Collection $collection)
    {
        $this->collection = $collection;
    }

    protected function prepareTotalCount(): int
    {
        return $this->collection->count();
    }

    /**
     * @return Collection
     */
    public function getList()
    {
        if ($this->collection->isEmpty()) {
            return $this->collection;
        }

        $collection = clone $this->collection;

        if ($this->isSortEnable()) {
            $sort = $this->getSort();
            $sortAttributes = array_reverse($sort->getCurrentSortWithAttribute()); // 倒序以保证多排的顺序正确
            foreach ($sortAttributes as $item) {
                $collection = $collection->sortBy(function ($value) use ($item) {
                    if ($item['attribute'] instanceof Expression) {
                        throw new NotSupportException('This Provider not support Expression Sort');
                    }
                    return $value[$item['attribute']];
                }, SORT_REGULAR, $item['sort'] === SORT_DESC);
            }
        }

        if ($this->isPaginatorEnable()) {
            $paginator = $this->getPaginator();
            $collection = $collection->forPage($paginator->getPage(), $paginator->getPageSize());
        }

        return $collection;
    }
}
