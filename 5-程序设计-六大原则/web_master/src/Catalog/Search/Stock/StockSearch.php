<?php

namespace App\Catalog\Search\Stock;

use App\Enums\Future\FuturesMarginAgreementStatus;
use App\Enums\Future\FuturesMarginDeliveryStatus;
use App\Enums\Future\FuturesMarginDeliveryType;
use App\Enums\Margin\MarginAgreementStatus;
use App\Catalog\Enums\Stock\StockSearchTypeEnum;
use App\Models\Delivery\CostDetail;
use App\Models\Futures\FuturesMarginAgreement;
use App\Models\Margin\MarginAgreement;
use App\Repositories\Stock\StockManagementRepository;
use Carbon\Carbon;
use Framework\DataProvider\QueryBuilderDataProvider;
use Framework\DataProvider\SearchModelTrait;
use Illuminate\Database\Concerns\BuildsQueries;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Query\JoinClause;

class StockSearch
{
    use SearchModelTrait;

    private $customerId;
    private $searchAttributes = [
        'filter_item_code_arr' => [],// item code 数组
        'filter_product_ids_arr' => [], // product ids 数组
        'filter_stock_type' => 0,
    ];

    public function __construct($customerId)
    {
        $this->customerId = $customerId;
    }

    public function search($params, $isDownload = false)
    {
        $this->loadAttributes($params);
        $query = $this->buildQuery($isDownload);
        $dataProvider = new QueryBuilderDataProvider($query);
        $dataProvider->setSort([
            'defaultOrder' => ['w_fee' => SORT_DESC, 'a_qty' => SORT_DESC],
            'alwaysOrder' => ['id' => SORT_ASC],
            'rules' => [
                'a_qty' => 'availableQty',
                'm_qty' => 'agreementQty',
                'l_qty' => 'blockQty',
                'w_fee' => 'feeTotal',
                'id' => 'product_id',
            ],
        ]);
        if ($isDownload) {
            $dataProvider->switchPaginator(false);
        } else {
            $dataProvider->setPaginator(['defaultPageSize' => 20,]);
        }
        return $dataProvider;
    }

    /**
     * 获取统计数据
     * @return array
     */
    public function getTotal(): array
    {
        $query = $this->buildQuery(true);
        $res = $query
            ->selectRaw('cast(sum(t.availableQty) as SIGNED) as a_qty')
            ->selectRaw('cast(sum(t.agreementQty) as SIGNED) as m_qty')
            ->selectRaw('cast(sum(t.blockQty) as SIGNED) as l_qty')
            ->selectRaw('sum(t.feeTotal) as w_fee')
            ->first();
        return [
            'a_qty' => $res && $res->a_qty >= 0 ? (int)$res->a_qty : 0,
            'm_qty' => $res && $res->m_qty >= 0 ? (int)$res->m_qty : 0,
            'l_qty' => $res && $res->l_qty >= 0 ? (int)$res->l_qty : 0,
            'w_fee' => $res && $res->w_fee >= 0 ? $res->w_fee : 0,
        ];
    }

    /**
     * 查看是否购买
     * @return bool
     */
    public function checkBuyHistory(): bool
    {
        $tempAttributes = $this->searchAttributes;
        // 回到初始状态
        $this->searchAttributes = ['filter_item_code_arr' => [], 'filter_stock_type' => 0,];
        $query = $this->buildMainQuery();
        $res = $query->exists();
        // 回到保存状态
        $this->searchAttributes = $tempAttributes;
        return $res;
    }

    /**
     * @param bool $forTotal 是否供下载使用 默认false
     * @return BuildsQueries|Builder
     */
    protected function buildQuery(bool $forTotal = false)
    {
        $stockService = app(StockManagementRepository::class);
        $query = $this->buildMainQuery();
        $sort = request('sort', '');
        $filterItemCode = $this->searchAttributes['filter_item_code_arr'] ?? [];
        $filterProductIds = $this->searchAttributes['filter_product_ids_arr'] ?? [];
        $rQuery = db(new Expression('(' . get_complete_sql($query) . ') as t'))->select(['t.*']);
        $filter_stock_type = (int)$this->searchAttributes['filter_stock_type'];
        // 可用库存 > 0 可用库存需要排序
        $subQuery = $stockService->buildProductCostQuery($this->customerId, $filterItemCode, $filterProductIds);
        $rQuery = $rQuery
            ->selectRaw('cast(ifnull(t1.availableQty,0) as SIGNED) as availableQty')
            ->leftJoin(new Expression('(' . get_complete_sql($subQuery) . ') as t1'), 't1.product_id', '=', 't.product_id')
            ->when($filter_stock_type & StockSearchTypeEnum::AVAILABLE_QTY, function ($q) {
                $q->where('t1.availableQty', '>', 0);
            });
        // 合约库存 > 0
        if ($forTotal || $filter_stock_type & StockSearchTypeEnum::AGREEMENT_QTY || (strpos($sort, 'm_qty') !== false)) {
            $subQuery2 = $stockService->buildContractQtyQuery($this->customerId, $filterItemCode, $filterProductIds);
            $rQuery = $rQuery
                ->selectRaw('cast(ifnull(t2.num,0) as SIGNED) as agreementQty')
                ->leftJoin(new Expression('(' . get_complete_sql($subQuery2) . ') as t2'), 't2.parent_product_id', '=', 't.product_id')
                ->when($filter_stock_type & StockSearchTypeEnum::AGREEMENT_QTY, function ($q) {
                    $q->where('t2.num', '>', 0);
                });
        }
        // 锁定库存 > 0
        if ($forTotal || $filter_stock_type & StockSearchTypeEnum::LOCK_QTY || (strpos($sort, 'l_qty') !== false)) {
            // 已售未发现在合并到锁定库存里 和之前的逻辑不一样
            $rQuery = $rQuery
                ->selectRaw('cast(if((t.onhandQty - ifnull(t1.availableQty,0)) > 0 , (t.onhandQty - ifnull(t1.availableQty,0)) , 0) as SIGNED) as blockQty')
                ->when($filter_stock_type & StockSearchTypeEnum::LOCK_QTY, function ($q) {
                    $q->whereRaw('(t.onhandQty - ifnull(t1.availableQty,0)) > 0');
                });
        }
        // 待支付仓租 > 0  待支付仓租需要排序
        $subQuery8 = $stockService->buildStorageFeeQuery($this->customerId, $filterItemCode, $filterProductIds);
        $rQuery = $rQuery
            ->selectRaw('ifnull(t8.fee_total,0) as feeTotal')
            ->leftJoin(new Expression('(' . get_complete_sql($subQuery8) . ') as t8'), 't8.product_id', '=', 't.product_id')
            ->when($filter_stock_type & StockSearchTypeEnum::FEE_ORDER_WAIT_PAY, function ($q) {
                $q->whereRaw('t8.fee_total > 0');
            });
        return db(new Expression('(' . get_complete_sql($rQuery) . ') as t'))->select(['t.*']);
    }

    public function buildMainQuery()
    {
        $filterItemCode = $this->searchAttributes['filter_item_code_arr'] ?? [];
        $filterProductIds = $this->searchAttributes['filter_product_ids_arr'] ?? [];
        // cost detail
        $query = CostDetail::query()
            ->alias('scd')
            ->select(['p.sku', 'p.image', 'p.product_id'])
            ->selectRaw('sum(scd.onhand_qty) as onhandQty')
            ->selectRaw('sum(scd.original_qty) as originalQty')
            ->leftJoin('oc_product as p', 'p.product_id', '=', 'scd.sku_id')
            ->where(['scd.buyer_id' => $this->customerId, 'p.product_type' => 0])
            ->whereExists(function ($q) { // 排除掉服务店铺
                $q->from('oc_customerpartner_to_product as c2p')
                    ->select('*')
                    ->whereRaw('c2p.product_id = p.product_id')
                    ->whereNotIn('c2p.customer_id', SERVICE_STORE_ARRAY);
            })
            ->when(!empty($filterItemCode), function ($q) use ($filterItemCode) {
                $q->whereIn('p.sku', $filterItemCode);
            })
            ->when(!empty($filterProductIds), function ($q) use ($filterProductIds) {
                $q->whereIn('p.product_id', $filterProductIds);
            });
        $query = $query->groupBy(['p.product_id']);
        // 现货保证金
        $subQuery1 = MarginAgreement::query()->alias('ma')
            ->leftJoin('tb_sys_margin_performer_apply AS tsmpa', function (JoinClause $left) {
                $left->on('tsmpa.agreement_id', '=', 'ma.id')
                    ->where('tsmpa.check_result', 1);
            })
            ->select(['p.sku', 'p.image', 'p.product_id'])
            ->selectRaw('"0" as onhandQty')
            ->selectRaw('"0" as originalQty')
            ->leftJoin('oc_product as p', 'p.product_id', '=', 'ma.product_id')
            ->where([
                'ma.status' => MarginAgreementStatus::SOLD,
            ])->where(function ($query) {
                // 作为主履约人或者共同履约人都算
                $query->where('ma.buyer_id', '=', $this->customerId)
                    ->orWhere('tsmpa.performer_buyer_id', '=', $this->customerId);
            })
            ->where('ma.expire_time', '>', Carbon::now())
            ->when(!empty($filterItemCode), function ($q) use ($filterItemCode) {
                $q->whereIn('p.sku', $filterItemCode);
            })
            ->when(!empty($filterProductIds), function ($q) use ($filterProductIds) {
                $q->whereIn('p.product_id', $filterProductIds);
            })
            ->groupBy(['p.product_id']);
        // 期货保证金
        $subQuery2 = FuturesMarginAgreement::query()->alias('fma')
            ->select(['p.sku', 'p.image', 'p.product_id'])
            ->selectRaw('"0" as onhandQty')
            ->selectRaw('"0" as originalQty')
            ->leftJoin('oc_product as p', 'fma.product_id', '=', 'p.product_id')
            ->leftJoin('oc_futures_margin_delivery as fd', 'fma.id', 'fd.agreement_id')
            ->where([
                'fma.agreement_status' => FuturesMarginAgreementStatus::SOLD,
                'fma.buyer_id' => $this->customerId,
                'fd.delivery_status' => FuturesMarginDeliveryStatus::TO_BE_PAID
            ])
            ->whereIn('fd.delivery_type', FuturesMarginDeliveryType::getNotToMargin())
            ->when(!empty($filterItemCode), function ($q) use ($filterItemCode) {
                $q->whereIn('p.sku', $filterItemCode);
            })
            ->when(!empty($filterProductIds), function ($q) use ($filterProductIds) {
                $q->whereIn('p.product_id', $filterProductIds);
            })
            ->groupBy(['p.product_id']);
        $query = $query->union($subQuery1)->union($subQuery2);
        return db(new Expression('(' . get_complete_sql($query) . ') as t'))
            ->select(['t.sku', 't.image', 't.product_id'])
            ->selectRaw('sum(t.onhandQty) as onhandQty')
            ->selectRaw('sum(t.originalQty) as originalQty')
            ->groupBy(['t.product_id']);
    }
}
