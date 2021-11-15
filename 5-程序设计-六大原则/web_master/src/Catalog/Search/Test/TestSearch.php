<?php

namespace App\Catalog\Search\Test;

use App\Models\Customer\Customer;
use Framework\DataProvider\QueryDataProvider;
use Framework\DataProvider\SearchModelTrait;

/**
 * @example 使用 DataProvider 的 Search 例子
 */
class TestSearch
{
    use SearchModelTrait;

    private $searchAttributes = [
        'customer_id' => '',
        'name' => '',
    ];

    public function search($params)
    {
        $this->loadAttributes($params);

        $query = Customer::query();
        $dataProvider = new QueryDataProvider($query);

        $query->filterWhere([
            'customer_id' => $this->searchAttributes['customer_id'],
            'nickname' => $this->searchAttributes['name'],
        ]);

        return $dataProvider;
    }
}
