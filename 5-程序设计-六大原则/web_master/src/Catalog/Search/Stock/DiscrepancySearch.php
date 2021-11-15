<?php

namespace App\Catalog\Search\Stock;

use App\Catalog\Enums\Stock\DiscrepancyReason;
use App\Catalog\Enums\Stock\DiscrepancyRecordType;
use App\Enums\Delivery\DeliveryLineType;
use App\Enums\Product\ProductType;
use App\Enums\SalesOrder\CustomerSalesOrderStatus;
use App\Enums\YzcRmaOrder\RmaOrderProductStatusRefund;
use App\Helper\CountryHelper;
use App\Models\Delivery\BuyerProductLock;
use App\Models\Delivery\CostDetail;
use App\Models\Delivery\DeliveryLine;
use App\Models\Rma\YzcRmaOrder;
use App\Models\SalesOrder\CustomerSalesOrder;
use App\Models\SalesOrder\CustomerSalesReorder;
use Carbon\Carbon;
use Framework\DataProvider\QueryBuilderDataProvider;
use Framework\DataProvider\SearchModelTrait;
use Framework\DataProvider\Sort;
use Framework\Model\Eloquent\Builder;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Query\JoinClause;

class DiscrepancySearch
{
    use SearchModelTrait;

    private $customerId;
    //支持的查询条件
    private $searchAttributes = [
        'filter_item_code' => '',
        'filter_type' => '',
        'filter_created_from' => '',
        'filter_created_to' => '',
        'filter_created_month' => '',
        'filter_product_id' => ''
    ];

    public function __construct($customerId)
    {
        $this->customerId = $customerId;
    }

    /**
     * @param $params
     * @param bool $isDownload
     * @return QueryBuilderDataProvider
     * @throws \Framework\Exception\InvalidConfigException
     */
    public function search($params, $isDownload = false)
    {
        $this->loadAttributes($params);

        $query = $this->buildQuery();
        $dataProvider = new QueryBuilderDataProvider($query);
        if (!$isDownload) {
            $dataProvider->setSort(new Sort([
                'defaultOrder' => ['creation_time' => SORT_DESC, 'product_id' => SORT_ASC],
                'alwaysOrder' => ['type' => SORT_DESC],
                'rules' => [
                    'creation_time' => 'creation_time',
                ],
            ]));
        } else {
            $dataProvider->switchSort(false);
            $dataProvider->switchPaginator(false);
            $query->orderByDesc('creation_time')->orderBy('product_id');
        }

        return $dataProvider;
    }

    /**
     * 判断是否有传入搜索条件
     * 必须先调用过loadAttributes
     *
     * @return bool
     */
    public function isSearch()
    {
        foreach ($this->searchAttributes as $searchAttribute) {
            if ($searchAttribute) {
                return true;
            }
        }
        return false;
    }

    /**
     * 总计
     *
     * @param $params
     * @return array
     */
    public function total($params)
    {
        $this->loadAttributes($params);
        $query = $this->buildQuery();
        $data = $query->groupBy(['type'])
            ->get(['type', new Expression('sum(quantity) AS count')]);
        $return = [];
        foreach (DiscrepancyRecordType::getViewItems() as $typeId => $typeDesc) {
            $return[$typeId] = [
                'type' => $typeId,
                'count' => 0,
                'desc' => 'Total ' . $typeDesc . ' Count'
            ];
        }
        foreach ($data as $item) {
            $return[$item->type]['count'] = $item->count;
        }

        return array_values($return);

    }

    /**
     * @return \Illuminate\Database\Concerns\BuildsQueries|\Illuminate\Database\Query\Builder|mixed
     */
    public function buildQuery()
    {
        //需要union的model，数组中key必须对应有build{Key}的方法构造model
        //值是查询条件对应的key
        //如果对子model增加条件，这里要相应的增加对应的key，以及修改filterWhere方法
        $unionModels = [
            'costDetailModel' => [
                'filter_item_code' => 'op.sku',
                'filter_product_id' => 'op.product_id',
            ],
            'yzcRmaOrderModel' => [
                'filter_item_code' => 'op.sku',
                'filter_product_id' => 'op.product_id',
            ],
            'customerSalesReorderModel' => [
                'filter_item_code' => 'csrl.item_code',
                'filter_product_id' => 'op.product_id',
            ],
            'payCustomerSalesOrderModel' => [
                'filter_item_code' => 'csol.item_code',
                'filter_product_id' => 'soa.product_id',
            ],
            'refundCustomerSalesOrderModel' => [
                'filter_item_code' => 'csol.item_code',
                'filter_product_id' => 'soa.product_id',
            ],
            'buyerProductLockModel' => [
                'filter_item_code' => 'op.sku',
                'filter_product_id' => 'bpl.product_id',
            ],
            'stockAdjustModel' => [
                'filter_item_code' => 'op.sku',
                'filter_product_id' => 'dl.ProductId',
            ]
        ];
        //剔除对应的模型
        if ($this->searchAttributes['filter_type'] == DiscrepancyRecordType::RECEIVING) {
            unset($unionModels['yzcRmaOrderModel'], $unionModels['customerSalesReorderModel']
                , $unionModels['payCustomerSalesOrderModel'], $unionModels['refundCustomerSalesOrderModel']
                , $unionModels['stockAdjustModel'], $unionModels['buyerProductLockModel']);
        } elseif (in_array($this->searchAttributes['filter_type'], [DiscrepancyRecordType::ALLOCATION, DiscrepancyRecordType::BLOCKED])) {
            unset($unionModels['costDetailModel']);
            if ($this->searchAttributes['filter_type'] === DiscrepancyRecordType::BLOCKED) {
                unset($unionModels['stockAdjustModel']);
            }
        }
        /** @var Builder $tempModel */
        $tempModel = null;
        //循环union model
        foreach ($unionModels as $modelName => $columns) {
            if ($tempModel) {
                $tempModel = $tempModel->unionAll($this->{"build" . $modelName}($columns));
            } else {
                $tempModel = $this->{"build" . $modelName}($columns);
            }
        }
        $tempModelSql = get_complete_sql($tempModel);

        //获取系统店铺
        $serviceStore = explode(',', substr(SERVICE_STORE, 1, strlen(SERVICE_STORE) - 2));

        return DB::table(new Expression("({$tempModelSql}) as temp"))
            ->whereNotIn('temp.seller_id', $serviceStore)
            ->when($this->searchAttributes['filter_created_from'], function ($query) {
                $query->where('temp.creation_time', '>=', $this->searchAttributes['filter_created_from']);
            })
            ->when($this->searchAttributes['filter_created_to'], function ($query) {
                $query->where('temp.creation_time', '<=', $this->searchAttributes['filter_created_to']);
            })
            ->when($this->searchAttributes['filter_created_month'], function ($query) {
                // 取这个月第一天
                $country = session('country');
                $formTime = Carbon::parse($this->searchAttributes['filter_created_month'], CountryHelper::getTimezoneByCode($country));
                // 取这个月最后一天的 23:59:59
                $toTime = (clone $formTime)->addMonth()->subSecond();
                // 转换成美国时间
                $query->whereBetween('temp.creation_time', [
                    $formTime->setTimezone(CountryHelper::getTimezone(223))->toDateTimeString(),
                    $toTime->setTimezone(CountryHelper::getTimezone(223))->toDateTimeString()
                ]);
            });
    }

    /**
     * 入库记录
     *
     * @param $columns
     * @return CostDetail|Builder|\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder
     */
    private function buildCostDetailModel($columns)
    {
        $model = CostDetail::query()->alias('scd')
            ->leftJoinRelations(['receiveLine as srl'])
            ->leftJoin('oc_order AS oo', function (JoinClause $left) {
                $left->on('oo.order_id', '=', 'srl.oc_order_id')
                    ->whereColumn('oo.customer_id', 'scd.buyer_id');
            })
            ->leftJoin('oc_product AS op', 'op.product_id', '=', 'scd.sku_id')
            ->leftJoin('oc_customerpartner_to_product AS ctp', 'ctp.product_id', '=', 'op.product_id')
            ->leftJoin('oc_customerpartner_to_customer AS ctc', 'ctc.customer_id', '=', 'ctp.customer_id')
            ->leftJoin('oc_yzc_rma_order AS yro', function (JoinClause $left) {
                $left->on('yro.id', '=', 'scd.rma_id')
                    ->whereColumn('yro.buyer_id', 'scd.buyer_id');
            })
            ->leftJoin('tb_sys_customer_sales_reorder AS csr', 'csr.rma_id', '=', 'yro.id')
            ->select([
                'scd.seller_id',
                'op.product_id',
                'op.sku',
                'oo.delivery_type',
                new Expression('CASE WHEN scd.rma_id IS NULL THEN oo.date_modified ELSE scd.create_time END AS creation_time'),
                new Expression("1 AS type"),
                'ctc.screenname',
                'scd.original_qty AS quantity',
                new Expression('CASE WHEN scd.rma_id IS NULL THEN ' . DiscrepancyReason::PURCHASE_ORDER . ' ELSE ' . DiscrepancyReason::RESHIPMENT_INBOUND . ' END AS reason'),
                new Expression('ifnull(srl.oc_order_id, csr.reorder_id ) AS order_id'),
                new Expression('CASE WHEN scd.rma_id IS NULL THEN scd.id ELSE yro.id END AS id'),
                new Expression("'CostDetail' AS model_type"),
            ])->where('scd.buyer_id', $this->customerId)->where('op.product_type', ProductType::NORMAL);
        $this->filterWhere($model, $columns);
        return $model;
    }

    /**
     * @param $columns
     *
     * @return Builder
     */
    private function buildYzcRmaOrderModel($columns)
    {
        $model = YzcRmaOrder::query()->alias('yro')
            ->leftJoinRelations(['yzcRmaOrderProduct as rop'])
            ->leftJoin('oc_product AS op', 'op.product_id', '=', 'rop.product_id')
            ->leftJoin('oc_customerpartner_to_product AS ctp', 'ctp.product_id', '=', 'op.product_id')
            ->leftJoin('oc_customerpartner_to_customer AS ctc', 'ctc.customer_id', '=', 'ctp.customer_id')
            ->select([
                'yro.seller_id',
                'op.product_id',
                'op.sku',
                new Expression('0 as delivery_type'),
                'yro.create_time AS creation_time',
                new Expression('CASE WHEN rop.status_refund = ' . RmaOrderProductStatusRefund::APPROVE . ' THEN ' . DiscrepancyRecordType::ALLOCATION . ' ELSE ' . DiscrepancyRecordType::BLOCKED . ' END AS type'),
                'ctc.screenname',
                'rop.quantity',
                new Expression('CASE WHEN rop.status_refund = ' . RmaOrderProductStatusRefund::APPROVE . ' THEN ' . DiscrepancyReason::RMA_REFUND . ' ELSE ' . DiscrepancyReason::BLOCKED_APPLYING_RMA . ' END AS reason'),
                'yro.rma_order_id AS order_id',
                'yro.id AS id',
                new Expression("'YzcRmaOrder' AS model_type"),
            ])
            ->where('yro.buyer_id', $this->customerId)->where('yro.order_type', 2)->where('yro.cancel_rma', 0)->where('op.product_type', ProductType::NORMAL);
        if ($this->searchAttributes['filter_type'] == DiscrepancyRecordType::ALLOCATION) {
            $model->where('rop.status_refund', '=', RmaOrderProductStatusRefund::APPROVE);
        } elseif ($this->searchAttributes['filter_type'] == DiscrepancyRecordType::BLOCKED) {
            $model->where('rop.status_refund', '=', RmaOrderProductStatusRefund::DEFAULT);
        } else {
            $model->where('rop.status_refund', '<>', RmaOrderProductStatusRefund::REJECT);
        }
        $this->filterWhere($model, $columns);
        return $model;
    }

    /**
     * @param $columns
     *
     * @return Builder
     */
    private function buildCustomerSalesReorderModel($columns)
    {
        $model = CustomerSalesReorder::query()->alias('csr')
            ->leftJoinRelations(['reorderLines as csrl', 'rmaOrder as yro'])
            ->leftJoin('tb_sys_cost_detail AS scd', function (JoinClause $left) {
                $left->on('scd.rma_id', '=', 'csr.rma_id')
                    ->whereColumn('scd.sku_id', 'csrl.product_id');
            })
            ->leftJoin('oc_product AS op', 'op.product_id', '=', 'scd.sku_id')
            ->leftJoin('oc_customerpartner_to_product AS ctp', 'ctp.product_id', '=', 'op.product_id')
            ->leftJoin('oc_customerpartner_to_customer AS ctc', 'ctc.customer_id', '=', 'ctp.customer_id')
            ->select([
                'yro.seller_id',
                'op.product_id',
                'csrl.item_code AS sku',
                new Expression('0 as delivery_type'),
                'scd.create_time',
                new Expression('CASE WHEN csr.order_status = ' . CustomerSalesOrderStatus::COMPLETED . ' THEN ' . DiscrepancyRecordType::ALLOCATION . ' ELSE ' . DiscrepancyRecordType::BLOCKED . ' END AS type'),
                'ctc.screenname',
                'csrl.qty AS quantity',
                new Expression('CASE WHEN csr.order_status = ' . CustomerSalesOrderStatus::COMPLETED . ' THEN ' . DiscrepancyReason::RESHIPMENT_OUTBOUND . ' WHEN csr.order_status = 2 THEN ' . DiscrepancyReason::RMA_BUT_NOT_SHIPPED . ' ELSE ' . DiscrepancyReason::BLOCKED_CANCELED_RMA_ORDER . ' END AS reason'),
                'csr.reorder_id AS order_id',
                'yro.id AS id',
                new Expression("'CustomerSalesReorder' AS model_type"),
            ])->where('csr.buyer_id', $this->customerId)->where('op.product_type', ProductType::NORMAL);

        if ($this->searchAttributes['filter_type'] == DiscrepancyRecordType::ALLOCATION) {
            $model->where('csr.order_status', 32);
        } elseif ($this->searchAttributes['filter_type'] == DiscrepancyRecordType::BLOCKED) {
            $model->where('csr.order_status', 2);
        } else {
            $model->whereIn('csr.order_status', [32, 2]);
        }
        $this->filterWhere($model, $columns);
        return $model;
    }

    /**
     * @param $columns
     *
     * @return Builder
     */
    private function buildPayCustomerSalesOrderModel($columns)
    {
        $allStatus = '(' . CustomerSalesOrderStatus::COMPLETED
            . ',' . CustomerSalesOrderStatus::BEING_PROCESSED
            . ',' . CustomerSalesOrderStatus::ON_HOLD
            . ',' . CustomerSalesOrderStatus::WAITING_FOR_PICK_UP
            . ',' . CustomerSalesOrderStatus::ASR_TO_BE_PAID
            . ',' . CustomerSalesOrderStatus::PENDING_CHARGES
            . ')';
        $model = CustomerSalesOrder::query()->alias('cso')
            ->leftJoinRelations(['lines as csol'])
            ->leftJoin('tb_sys_order_associated AS soa', 'soa.sales_order_line_id', '=', 'csol.id')
            ->leftJoin('oc_customerpartner_to_product AS ctp', 'ctp.product_id', '=', 'soa.product_id')
            ->leftJoin('oc_customerpartner_to_customer AS ctc', 'ctc.customer_id', '=', 'ctp.customer_id')
            ->leftJoin('oc_order_cloud_logistics AS cl', 'cl.order_id', '=', 'soa.order_id')
            ->leftJoin('oc_order AS oo', 'oo.order_id', '=', 'soa.order_id')
            ->leftJoin('oc_product AS op', 'op.product_id', '=', 'soa.product_id')
            ->select([
                'soa.seller_id',
                'soa.product_id',
                'csol.item_code AS sku',
                'oo.delivery_type',
                new Expression('CASE WHEN max( oo.date_modified ) > cso.create_time THEN max( oo.date_modified ) ELSE cso.create_time END AS creation_time'),
                new Expression('CASE WHEN cso.order_status = ' . CustomerSalesOrderStatus::COMPLETED . ' THEN ' . DiscrepancyRecordType::ALLOCATION . ' ELSE ' . DiscrepancyRecordType::BLOCKED . ' END AS type'),
                'ctc.screenname',
                new Expression('sum( CASE WHEN soa.seller_id NOT IN ' . SERVICE_STORE . ' THEN soa.qty ELSE 0 END ) AS quantity'),
                new Expression('CASE WHEN cl.id IS NOT NULL THEN ' . DiscrepancyReason::CWF_ORDER . '
                            WHEN cso.order_status = ' . CustomerSalesOrderStatus::COMPLETED . ' THEN ' . DiscrepancyReason::SALES_ORDER . '
                            WHEN cso.order_status = ' . CustomerSalesOrderStatus::BEING_PROCESSED . ' THEN ' . DiscrepancyReason::SOLD_BUT_NOT_SHIPPED . '
                            WHEN cso.order_status = ' . CustomerSalesOrderStatus::ON_HOLD . ' THEN ' . DiscrepancyReason::SOLD_BUT_NOT_SHIPPED . '
                            WHEN cso.order_status = ' . CustomerSalesOrderStatus::WAITING_FOR_PICK_UP . ' THEN ' . DiscrepancyReason::SOLD_BUT_NOT_SHIPPED . '
			                WHEN cso.order_status = ' . CustomerSalesOrderStatus::ASR_TO_BE_PAID . ' THEN ' . DiscrepancyReason::BLOCKED_ASR . '
                            WHEN cso.order_status = ' . CustomerSalesOrderStatus::PENDING_CHARGES . ' THEN ' . DiscrepancyReason::BLOCKED_PENDING_CHARGES . '
                            END AS reason'),
                new Expression("CASE WHEN cso.order_status in {$allStatus} THEN cso.order_id  END AS order_id"),
                new Expression("CASE WHEN cl.id IS NOT NULL THEN cl.id WHEN cso.order_status in {$allStatus} THEN cso.id END AS id "),
                new Expression("'PayCustomerSalesOrder' AS model_type"),
            ])
            ->where('cso.buyer_id', $this->customerId)->whereNotNull('soa.id')->where('op.product_type', ProductType::NORMAL);
        //欧洲补运费产品
        $europeFreightProductId = json_decode(configDB('europe_freight_product_id'));
        if ($europeFreightProductId) {
            $model->whereNotIn('soa.product_id', $europeFreightProductId);
        }
        if ($this->searchAttributes['filter_type'] == DiscrepancyRecordType::ALLOCATION) {
            $model->where('cso.order_status', CustomerSalesOrderStatus::COMPLETED);
        } elseif ($this->searchAttributes['filter_type'] == DiscrepancyRecordType::BLOCKED) {
            $model->whereIn('cso.order_status',
                [
                    CustomerSalesOrderStatus::BEING_PROCESSED,
                    CustomerSalesOrderStatus::ASR_TO_BE_PAID,
                    CustomerSalesOrderStatus::PENDING_CHARGES,
                    CustomerSalesOrderStatus::ON_HOLD,
                    CustomerSalesOrderStatus::WAITING_FOR_PICK_UP,
                ]);
        } else {
            $model->whereIn('cso.order_status',
                [
                    CustomerSalesOrderStatus::BEING_PROCESSED,
                    CustomerSalesOrderStatus::COMPLETED,
                    CustomerSalesOrderStatus::ASR_TO_BE_PAID,
                    CustomerSalesOrderStatus::PENDING_CHARGES,
                    CustomerSalesOrderStatus::ON_HOLD,
                    CustomerSalesOrderStatus::WAITING_FOR_PICK_UP,
                ]);
        }
        $this->filterWhere($model, $columns);
        //按明细和采购单拆分，因为有同一个销售单的同一个sku绑定多个采购单的情况
        return $model->groupBy(['csol.id', 'soa.order_id']);
    }

    /**
     * cancel销售订单的返金出库
     *
     * @param $columns
     *
     * @return Builder
     */
    private function buildRefundCustomerSalesOrderModel($columns)
    {
        // 这里是不是可以考虑优化下
        $childWhere = DB::table('vw_rma_order_info AS vroi')->select(new Expression('count(1)'))
            ->whereColumn('vroi.from_customer_order_id', '=', 'roi.from_customer_order_id')
            ->where('vroi.status_refund', 1)
            ->where('vroi.processed_date', '<', new Expression("IF (roi.processed_date IS NULL, '9999-01-01', roi.processed_date)"));
        $childWhereSql = get_complete_sql($childWhere);
        $model = CustomerSalesOrder::query()->alias('cso')
            ->leftJoinRelations(['lines as csol'])
            ->leftJoin('tb_sys_order_associated AS soa', function (JoinClause $join) {
                $join->on('soa.sales_order_line_id', '=', 'csol.id')
                    ->whereColumn('soa.buyer_id', 'cso.buyer_id');
            })
            ->leftJoin('oc_customerpartner_to_product AS ctp', 'ctp.product_id', '=', 'soa.product_id')
            ->leftJoin('oc_customerpartner_to_customer AS ctc', 'ctc.customer_id', '=', 'ctp.customer_id')
            ->leftJoin('vw_rma_order_info AS roi', function (JoinClause $join) {
                $join->on('roi.from_customer_order_id', '=', 'cso.order_id')
                    ->whereColumn('roi.buyer_id', 'cso.buyer_id')
                    ->whereColumn('roi.product_id', 'soa.product_id')
                    ->where('roi.cancel_status', '0')
                    ->where('roi.status_refund', '<>', '2');
            })
            ->leftJoin('oc_order AS oo', 'oo.order_id', '=', 'soa.order_id')
            ->leftJoin('oc_product AS op', 'op.product_id', '=', 'soa.product_id')
            ->select([
                'soa.seller_id',
                'soa.product_id',
                'csol.item_code AS sku',
                'oo.delivery_type',
                new Expression('CASE WHEN max( oo.date_modified ) > cso.create_time THEN max( oo.date_modified ) ELSE cso.create_time END AS creation_time'),
                new Expression('CASE WHEN roi.status_refund = ' . RmaOrderProductStatusRefund::APPROVE . ' THEN ' . DiscrepancyRecordType::ALLOCATION . ' ELSE ' . DiscrepancyRecordType::BLOCKED . ' END AS type'),
                'ctc.screenname',
                new Expression('CASE WHEN soa.seller_id NOT IN ' . SERVICE_STORE . ' THEN sum(soa.qty) ELSE 0 END AS quantity'),
                new Expression('CASE WHEN roi.status_refund = ' . RmaOrderProductStatusRefund::APPROVE . ' THEN ' . DiscrepancyReason::RMA_REFUND . ' WHEN roi.rma_id IS NULL OR roi.status_refund = ' . RmaOrderProductStatusRefund::REJECT . ' THEN ' . DiscrepancyReason::BLOCKED_CANCELED_SALES_ORDER . ' ELSE ' . DiscrepancyReason::BLOCKED_APPLYING_RMA . ' END AS reason'),
                new Expression("CASE WHEN roi.rma_id IS NULL OR roi.status_refund = 2 THEN cso.order_id ELSE roi.rma_order_id END AS order_id"),
                new Expression('CASE WHEN roi.status_refund <> ' . RmaOrderProductStatusRefund::REJECT . ' THEN roi.rma_id ELSE cso.id END AS id '),
                new Expression("'RefundCustomerSalesOrder' AS model_type"),
            ])
            ->where('cso.buyer_id', $this->customerId)->whereNotNull('soa.id')->where('cso.order_status', CustomerSalesOrderStatus::CANCELED)->where('op.product_type', ProductType::NORMAL)
            ->whereRaw("({$childWhereSql}) = 0");

        if ($this->searchAttributes['filter_type'] == DiscrepancyRecordType::ALLOCATION) {
            $model->where('roi.status_refund', RmaOrderProductStatusRefund::APPROVE);
        } elseif ($this->searchAttributes['filter_type'] == DiscrepancyRecordType::BLOCKED) {
            $model->where(function (Builder $query) {
                $query->where('roi.status_refund', '<>', RmaOrderProductStatusRefund::APPROVE)
                    ->orWhereNull('roi.status_refund');
            });
        }
        $this->filterWhere($model, $columns);
        return $model->groupBy(['csol.id', 'roi.rma_id']);
    }


    /**
     * 查询库存下调和库存盘亏的记录,目前就是FBA和BO出库的记录
     *
     * @param $columns
     * @return DeliveryLine|Builder
     */
    private function buildStockAdjustModel($columns)
    {
        $model = DeliveryLine::query()->alias('dl')
            ->leftJoinRelations(['costDetail as cost', 'product as op'])
            ->leftJoin('oc_customerpartner_to_customer as ctc', 'cost.seller_id', '=', 'ctc.customer_id')
            ->select([
                'cost.seller_id',
                'dl.ProductId',
                'op.sku AS sku',
                new Expression('0 AS delivery_type'),
                'dl.create_time AS creation_time',
                new Expression(DiscrepancyRecordType::ALLOCATION . ' AS type'),
                'ctc.screenname AS screenname',
                'dl.DeliveryQty AS quantity',
                new Expression('CASE WHEN dl.type in (' . DeliveryLineType::BO . ',' . DeliveryLineType::INVENTORY_LOSSES . ') THEN ' . DiscrepancyReason::INVENTORY_DEFICIT . ' ELSE ' . DiscrepancyReason::INVENTORY_REDUCTION . ' END AS reason'),
                new Expression("'' AS order_id"),
                new Expression("'' AS id"),
                new Expression("'StockAdjust' AS model_type"),
            ])
            ->where('cost.buyer_id', $this->customerId)
            ->where(function (Builder $q) {
                $q->where(function (Builder $q) {
                    $q->where('dl.SalesHeaderId', 0)->where('dl.SalesLineId', 0)->whereIn('dl.type', [DeliveryLineType::GENERAL, DeliveryLineType::BO]);
                })->orWhere(function (Builder $q) {
                    $q->where('dl.type', DeliveryLineType::INVENTORY_LOSSES);
                });
            })
            ->where('op.product_type', ProductType::NORMAL);

        $this->filterWhere($model, $columns);
        return $model;
    }

    /**
     * buyer锁定库存
     *
     * @param $columns
     * @return BuyerProductLock|Builder|\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder
     */
    public function buildBuyerProductLockModel($columns)
    {
        $model = BuyerProductLock::query()
            ->alias('bpl')
            ->leftJoin('oc_product AS op', 'op.product_id', '=', 'bpl.product_id')
            ->leftJoin('oc_customerpartner_to_product AS ctp', 'ctp.product_id', '=', 'bpl.product_id')
            ->leftJoin('oc_customerpartner_to_customer AS ctc', 'ctc.customer_id', '=', 'ctp.customer_id')
            ->leftJoin('tb_sys_order_associated_pre as oap', 'oap.id', '=', 'bpl.foreign_key')
            ->leftJoin('tb_sys_customer_sales_order as sa', 'oap.sales_order_id', '=', 'sa.id')
            ->select(
                [
                    'ctp.customer_id as seller_id',
                    'bpl.product_id',
                    'op.sku AS sku',
                    new Expression('0 AS delivery_type'),
                    'bpl.create_time AS creation_time',
                    new Expression(DiscrepancyRecordType::BLOCKED . ' AS type'),
                    'ctc.screenname AS screenname',
                    'bpl.qty AS quantity',
                    new Expression(
                        'case bpl.type' .
                        ' when 1 then ' . DiscrepancyReason::BUYER_INVENTORY_REDUCTION_LOCK .
                        ' when 2 then ' . DiscrepancyReason::BUYER_INVENTORY_DEFICIT_LOCK .
                        ' when 3 then ' . DiscrepancyReason::BUYER_SALES_ORDER_PRE_LOCK .
                        ' end AS reason'
                    ),
                    new Expression('case bpl.type when 3 then sa.order_id else "" end AS order_id'),
                    new Expression('bpl.id'),
                    new Expression("'StockAdjust' AS model_type"),
                ]
            )
            ->where('bpl.is_processed', 0);

        $this->filterWhere($model, $columns);
        return $model;
    }

    /**
     * 为model注入条件
     *
     * @param $model
     * @param $columns
     * @return mixed
     */
    private function filterWhere($model, $columns)
    {
        return $model->filterWhere([
            [$columns['filter_item_code'], '=', $this->searchAttributes['filter_item_code']],
            [$columns['filter_product_id'], '=', $this->searchAttributes['filter_product_id']]
        ]);
    }
}
