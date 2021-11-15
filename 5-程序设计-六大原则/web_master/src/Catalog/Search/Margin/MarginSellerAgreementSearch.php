<?php

namespace App\Catalog\Search\Margin;

use App\Enums\Margin\MarginAgreementStatus;
use App\Models\Margin\MarginAgreement;
use Framework\DataProvider\Paginator;
use Framework\DataProvider\QueryDataProvider;
use Framework\DataProvider\SearchModelTrait;
use Framework\DataProvider\Sort;
use Illuminate\Database\Eloquent\Builder;

class MarginSellerAgreementSearch
{
    use SearchModelTrait;

    private $customerId;
    private $searchAttributes = [
        'filter_agreement_no' => '',
        'filter_status' => '',
        'filter_sku_mpn' => '',
        'filter_buyer_name' => '',
        'filter_date_from' => '',
        'filter_date_to' => '',
        'filter_margin_hot_map' => '',
    ];

    public function __construct($customerId)
    {
        $this->customerId = $customerId;
    }

    /**
     * @param $params
     * @param $agreementIds
     * @param bool $isDownload
     * @return QueryDataProvider
     * @throws \Framework\Exception\InvalidConfigException
     */
    public function search($params, $agreementIds, $isDownload = false)
    {
        $this->loadAttributes(array_map('trim', $params));
        $query = $this->buildQuery($agreementIds);
        $dataProvider = new QueryDataProvider($query);
        if (!$isDownload) {
            $dataProvider->setSort(new Sort([
                'defaultOrder' => ['last_modified' => SORT_DESC],
                'rules' => [
                    'last_modified' => 'a.update_time',
                    'effect_time' => 'a.effect_time',
                ],
            ]));
            $dataProvider->setPaginator(new Paginator([
                'defaultPageSize' => 10,
            ]));
        } else {
            $dataProvider->switchSort(false);
            $dataProvider->switchPaginator(false);
            $query->orderByDesc('a.update_time');
        }

        return $dataProvider;
    }

    /**
     * 构建
     * @param $agreementIds
     * @return Builder
     */
    protected function buildQuery($agreementIds)
    {
        $query = MarginAgreement::query()->alias('a')
            ->with(['marginStatus', 'process', 'process.relateOrders', 'performerApplies', 'futureDelivery', 'buyer', 'buyer.buyer'])
            ->join('oc_customer as c', 'a.buyer_id', '=', 'c.customer_id')
            ->join('oc_product as p', 'a.product_id', '=', 'p.product_id')
            ->where('a.seller_id', $this->customerId)
            ->visible();

        $query = $this->filterBuild($query);

        // 协议Id过滤
        if (!empty($agreementIds)) {
            $query->whereIn('a.id', $agreementIds);
        }

        return $query->select(['a.*', 'c.nickname', 'c.user_number', 'c.customer_group_id']);
    }

    /**
     * 获取统计项的协议IDs
     * @param $params
     * @param bool $needFilter
     * @return array
     */
    public function getStatAgreementIds(array $params = [], bool $needFilter = true) :array
    {
        if (!empty($params)) {
            $this->loadAttributes($params);
        }

        $query = MarginAgreement::query()->alias('a')
            ->join('oc_customer as c', 'a.buyer_id', '=', 'c.customer_id')
            ->join('oc_product as p', 'a.product_id', '=', 'p.product_id')
            ->where('a.seller_id', $this->customerId)
            ->visible();

        if ($needFilter) {
            $query = $this->filterBuild($query);
        }

        return [
            'to_be_processed' => (clone $query)
                ->whereIn('a.status', MarginAgreementStatus::toBeProcessedStatus())
                ->pluck('a.id')->unique()->toArray(),
            'margin_deposit_to_be_paid' => (clone $query)
                ->whereIn('a.status', MarginAgreementStatus::marginDepositToBePaidStatus())
                ->pluck('a.id')->unique()->toArray(),
            'due_payment_to_paid' => (clone $query)
                ->whereIn('a.status', MarginAgreementStatus::duePaymentToPaidStatus())
                ->pluck('a.id')->unique()->toArray(),
            'to_be_expired' => (clone $query)
                ->whereIn('a.status', MarginAgreementStatus::duePaymentToPaidStatus())
                //当前国别时间距离协议结束日期7天的To be paid尾款数据
                ->whereRaw("TIMESTAMPDIFF(DAY, NOW(), a.expire_time) < 7 and TIMESTAMPDIFF(DAY, NOW(), a.expire_time) >= 0 and a.expire_time >= NOW()")
                ->pluck('a.id')->unique()->toArray(),
        ];
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    private function filterBuild(Builder $query)
    {
        // 协议号过滤
        if (!empty($this->searchAttributes['filter_agreement_no'])) {
            $query->where('a.agreement_id', 'like', '%' . $this->searchAttributes['filter_agreement_no'] . '%');
        }

        // 状态过滤
        if (!empty($this->searchAttributes['filter_status'])) {
            $query->where('a.status', $this->searchAttributes['filter_status']);
        }

        // sku/mpn过滤
        if (!empty($this->searchAttributes['filter_sku_mpn'])) {
            $query->where(function ($q) {
                $q->where('p.sku', 'like', '%' . $this->searchAttributes['filter_sku_mpn'] . '%')
                    ->orWhere('p.mpn', 'like', '%' . $this->searchAttributes['filter_sku_mpn'] . '%');
            });
        }

        // 生效时间开始过滤
        if (!empty($this->searchAttributes['filter_date_from'])) {
            $query->where('a.effect_time', '>=', $this->searchAttributes['filter_date_from']);
        }

        // 过期时间结束过滤
        if (!empty($this->searchAttributes['filter_date_to'])) {
            $query->where('a.expire_time', '<=', $this->searchAttributes['filter_date_to']);
        }

        // 客户名称过滤
        if (!empty($this->searchAttributes['filter_buyer_name'])) {
            $query->where(function ($q) {
                $q->where('c.nickname', 'like', '%' . $this->searchAttributes['filter_buyer_name'] . '%')
                    ->orWhere('c.user_number', 'like', '%' . $this->searchAttributes['filter_buyer_name'] . '%');
            });
        }

        return $query;
    }
}
