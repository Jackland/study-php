<?php

namespace Framework\DataProvider;

use Illuminate\Support\Collection;

class ArrayDataProvider extends CollectionDataProvider
{
    public function __construct(array $list)
    {
        parent::__construct(Collection::make($list));
    }

    /**
     * @return array
     */
    public function getList()
    {
        return array_values(parent::getList()->toArray());
    }
}
