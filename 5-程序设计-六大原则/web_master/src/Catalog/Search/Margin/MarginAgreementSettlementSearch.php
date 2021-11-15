<?php

namespace App\Catalog\Search\Margin;

use App\Models\Order\OrderProduct;
use Framework\DataProvider\QueryDataProvider;
use Framework\DataProvider\SearchModelTrait;
use Framework\DataProvider\Paginator;

class MarginAgreementSettlementSearch
{
    use SearchModelTrait;

    private $agreementId;
    private $productId;

    private $searchAttributes = [
    ];

    public function __construct($agreementId, $productId)
    {
        $this->productId = $productId;
        $this->agreementId = $agreementId;
    }

    /**
     * @param $params
     * @return QueryDataProvider
     * @throws \Framework\Exception\InvalidConfigException
     */
    public function search($params)
    {
        $this->loadAttributes($params);
        $query = $this->buildQuery();
        $dataProvider = new QueryDataProvider($query);
        $dataProvider->setPaginator(['defaultPageSize' => 10]);
        $dataProvider->switchSort(false);
        $query->orderByDesc('oop.order_product_id');

        return $dataProvider;
    }

    protected function buildQuery()
    {
        return OrderProduct::query()->alias('oop')
            ->leftJoinRelations('order as oo')
            ->leftJoin('tb_sys_margin_order_relation as mor', 'mor.rest_order_id', '=', 'oop.order_id')
            ->leftJoin('oc_customerpartner_to_product as ctp', 'ctp.product_id', '=', 'oop.product_id')
            ->leftJoin('oc_customerpartner_to_customer as ctc', 'ctc.customer_id', '=', 'ctp.customer_id')
            ->leftJoin('tb_sys_margin_process as mp', 'mp.id', '=', 'mor.margin_process_id')
            ->where('oop.agreement_id', $this->agreementId)
            ->where('oop.type_id', 2)
            ->where('oop.product_id', $this->productId)
            ->whereNotNull('mor.rest_order_id')
            ->where('mp.margin_id', $this->agreementId)
            ->selectRaw("
            oop.*,
            oo.customer_id as buyer_id,
            ctc.screenname as store_name,
            oo.delivery_type,
            oo.date_added,
            mor.create_time");
    }

}
