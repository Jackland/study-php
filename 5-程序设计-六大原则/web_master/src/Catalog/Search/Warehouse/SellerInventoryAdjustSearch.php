<?php

namespace App\Catalog\Search\Warehouse;

use App\Enums\Warehouse\SellerInventoryAdjustType;
use App\Models\Warehouse\SellerInventoryAdjust;
use Framework\DataProvider\QueryDataProvider;
use Framework\DataProvider\SearchModelTrait;
use Framework\DataProvider\Sort;
use Framework\Model\Eloquent\Query\Builder;

class SellerInventoryAdjustSearch
{
    use SearchModelTrait;

    private $customerId;
    private $searchAttributes = [
        'sku_or_mpn' => '',
        'status' => '',
        'create_time_start' => '',
        'create_time_end' => '',
    ];

    public function __construct($customerId)
    {
        $this->customerId = $customerId;
    }

    public function search($params)
    {
        $this->loadAttributes($params);

        $query = $this->buildQuery();
        $dataProvider = new QueryDataProvider($query);
        $dataProvider->setSort(new Sort([
            'rules' => [
                'inventory_id' => 'inventory_id',
            ],
            'defaultOrder' => ['inventory_id' => SORT_DESC],
        ]));
        return $dataProvider;
    }

    protected function buildQuery()
    {
        return SellerInventoryAdjust::query()
            ->with('adjustDetail')
            ->when($this->searchAttributes['sku_or_mpn'], function ($q) {
                $q->whereHas('adjustDetail.product', function ($q) {
                    $q->where('oc_product.sku', 'like', "%{$this->searchAttributes['sku_or_mpn']}%")
                        ->orWhere('oc_product.mpn', 'like', "%{$this->searchAttributes['sku_or_mpn']}%");
                });
            })
            ->where('customer_id', $this->customerId)
            ->where('transaction_type', SellerInventoryAdjustType::LOSE)
            ->filterWhere([
                ['status', '=', $this->searchAttributes['status']],
                ['create_time', '>', $this->searchAttributes['create_time_start']],
                ['create_time', '<', $this->searchAttributes['create_time_end']],
            ]);
    }

}
