<?php

namespace App\Catalog\Search\Margin;

use App\Enums\Common\YesNoEnum;
use App\Models\Margin\MarginContract;
use Framework\DataProvider\Paginator;
use Framework\DataProvider\QueryDataProvider;
use Framework\DataProvider\SearchModelTrait;
use Framework\DataProvider\Sort;

class ContractSearch
{
    use SearchModelTrait;

    private $customerId;

    private $searchAttributes = [
        'filter_sku_or_mpn' => '',
    ];

    public function __construct($customerId)
    {
        $this->customerId = $customerId;
    }

    public function search($params, $isDownload = false)
    {
        $this->loadAttributes($params);

        $query = $this->buildQuery();

        $dataProvider = new QueryDataProvider($query);

        if (!$isDownload) {
            $dataProvider->setSort(new Sort([
                'defaultOrder' => ['update_time' => SORT_DESC],
                'rules' => [
                    'update_time' => 'c.update_time',
                ],
            ]));

            $dataProvider->setPaginator(new Paginator([
                'defaultPageSize' => 10,
            ]));
        } else {
            $dataProvider->switchSort(false);
            $dataProvider->switchPaginator(false);
            $query->orderByDesc('c.update_time');
        }

        return $dataProvider;
    }

    protected function buildQuery()
    {
        return MarginContract::query()->alias('c')
            ->select(['c.*', 'p.mpn', 'p.sku'])
            ->with('templates')
            ->where('c.customer_id', $this->customerId)
            ->where('c.status', 1)
            ->where('c.is_deleted', YesNoEnum::NO)
            ->join('oc_product as p', 'c.product_id', '=', 'p.product_id')
            ->when(!empty($this->searchAttributes['filter_sku_or_mpn']), function ($q) {
                $q->where(function ($query) {
                    $query->where('p.sku', 'like', '%' . $this->searchAttributes['filter_sku_or_mpn'] . '%')
                        ->orWhere('p.mpn', 'like', '%' . $this->searchAttributes['filter_sku_or_mpn'] . '%');
                });
            });
    }
}
