<?php

namespace App\Catalog\Search\Test;

use App\Models\Customer\Customer;
use Framework\DataProvider\QueryDataProvider;
use Framework\Model\RequestForm\RequestForm;

/**
 * @example search 结合 RequestForm 的例子
 */
class TestWithRequestFormSearch extends RequestForm
{
    public $customer_id;
    public $name;

    /**
     * @inheritDoc
     */
    protected function getRules(): array
    {
        return [
            'customer_id' => 'integer',
            'name' => 'string',
        ];
    }

    /**
     * @inheritDoc
     */
    protected function getAutoLoadRequestData()
    {
        return $this->request->get();
    }

    public function search()
    {
        $query = Customer::query();
        $dataProvider = new QueryDataProvider($query);
        $dataProvider->setSort([
            'rules' => [
                'customer_id',
            ],
        ]);
        $dataProvider->setPaginator([
            'defaultPageSize' => 10,
        ]);

        if (!$this->isValidated()) {
            // 校验不通过时，不返回任何数据
            //$query->where('1=0');
            return $dataProvider;
        }

        $query->filterWhere([
            'customer_id' => $this->customer_id,
            'nickname' => $this->name,
        ]);

        return $dataProvider;
    }
}
