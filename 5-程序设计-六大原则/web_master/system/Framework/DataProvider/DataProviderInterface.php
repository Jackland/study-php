<?php

namespace Framework\DataProvider;

interface DataProviderInterface
{
    public function getSort(): Sort;

    public function getPaginator(): Paginator;

    /**
     * @return mixed
     */
    public function getList();

    public function getTotalCount(): int;
}
