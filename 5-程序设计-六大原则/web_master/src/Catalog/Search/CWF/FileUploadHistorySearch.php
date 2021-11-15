<?php

namespace App\Catalog\Search\CWF;

use App\Models\CWF\CloudWholesaleFulfillmentFileUpload;
use Framework\DataProvider\QueryDataProvider;
use Framework\DataProvider\SearchModelTrait;
use Framework\Exception\InvalidConfigException;

class FileUploadHistorySearch
{
    use SearchModelTrait;

    private $customerId;
    private $searchAttributes = [
        'filter_orderDate_from' => null,
        'filter_orderDate_to' => null,
    ];

    public function __construct($customerId)
    {
        $this->customerId = $customerId;
    }

    /**
     * @throws InvalidConfigException
     */
    public function search($params): QueryDataProvider
    {
        $this->loadAttributes($params);
        $query = $this->buildQuery();
        $dataProvider = new QueryDataProvider($query);
        $dataProvider->setSort(['alwaysOrder' => ['id' => SORT_DESC], 'rules' => ['id' => 'id']]);
        $dataProvider->setPaginator(['defaultPageSize' => 20, 'pageParam' => 'page_num',]);
        return $dataProvider;
    }

    private function buildQuery()
    {
        return CloudWholesaleFulfillmentFileUpload::query()
            ->where('create_id', $this->customerId)
            ->with(['fileUpload'])
            ->when($this->searchAttributes['filter_orderDate_from'], function ($q) {
                $q->where('create_time', '>=', $this->searchAttributes['filter_orderDate_from']);
            })
            ->when($this->searchAttributes['filter_orderDate_to'], function ($q) {
                $q->where('create_time', '<=', $this->searchAttributes['filter_orderDate_to']);
            });
    }

}
