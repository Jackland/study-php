<?php

namespace App\Catalog\Search\FeeOrder;

use App\Enums\FeeOrder\FeeOrderFeeType;
use App\Models\FeeOrder\FeeOrder;
use Framework\DataProvider\QueryDataProvider;
use Framework\DataProvider\SearchModelTrait;
use Framework\DataProvider\Sort;

class FeeOrderSearch
{
    use SearchModelTrait;

    private $customerId;
    private $searchAttributes = [
        'fee_order_id' => '',
        'filterOrderId' => '',// #4297 改为只传这一个id，绝对匹配
        'feePurchaseOrderId' => '',// 前端已不传，但是保留此查询
        'feeBindOrderID' => '',// 前端已不传，但是保留此查询
        'feeItemCode' => '',// 前端已不传，但是保留此查询
        'feeOrderStatus' => '-1',
        'feeCreationDateFrom' => '',
        'feeCreationDateTo' => '',
        'feeType' => '',
        'feeCreationDateRange' => 'anytime', // 仅用于记录请求，用于前端数据恢复，不做查询
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
                'defaultOrder' => ['create_time' => SORT_DESC],
                'alwaysOrder' => ['id' => SORT_ASC],
                'rules' => [
                    'create_time' => 'fo.created_at',
                    'status' => 'fo.status',
                    'checkout_time' => 'fo.paid_at',
                    'id' => 'fo.id',
                ],
            ]));
        } else {
            $dataProvider->switchSort(false);
            $dataProvider->switchPaginator(false);
            $query->orderBy('fo.status')
                ->orderBy('fo.created_at')
                ->orderBy('fo.order_no');
        }

        return $dataProvider;
    }

    /**
     * 校验当前用户是否已经有费用单
     * @return bool
     */
    public function checkFeeOrderExists()
    {
        return FeeOrder::query()->where('buyer_id', $this->customerId)->exists();
    }

    protected function buildQuery()
    {
        // 实质上有一个费用类型 暂时无需考虑
        return FeeOrder::query()->alias('fo')
            ->with(['orderInfo', 'storageDetails', 'safeguardDetails', 'safeguardDetails.safeguardConfig'])
            ->select(['fo.*'])
            ->leftJoin('oc_fee_order_storage_detail  as fd', 'fo.id', '=', 'fd.fee_order_id')
            ->leftJoin('oc_storage_fee as sf', 'fd.storage_fee_id', '=', 'sf.id')
            ->leftJoin('tb_sys_customer_sales_order as cso', 'fo.order_id', '=', 'cso.id')
            ->leftJoin('oc_yzc_rma_order as yro', 'fo.order_id', '=', 'yro.id')
            ->where('fo.buyer_id', $this->customerId)
            ->where('fo.is_show', 1)
            ->where(function ($query) {
                $query->where(function ($query2) {
                    $query2->where('fo.fee_type', FeeOrderFeeType::STORAGE)
                        ->where('fo.fee_total', '>', 0);
                })->orWhere('fo.fee_type', '<>', FeeOrderFeeType::STORAGE);
            })
            ->filterWhere([
                ['fo.order_no', 'like', "%{$this->searchAttributes['feePurchaseOrderId']}%"],
                ['sf.product_sku', 'like', "%{$this->searchAttributes['feeItemCode']}%"],
                ['fo.created_at', '>', $this->searchAttributes['feeCreationDateFrom']],
                ['fo.created_at', '<', $this->searchAttributes['feeCreationDateTo']],
                ['fo.fee_type', '=', $this->searchAttributes['feeType']],
            ])
            ->when($this->searchAttributes['feeOrderStatus'] != '-1' && $this->searchAttributes['feeOrderStatus'] !== '', function ($q) {
                $q->where('fo.status', $this->searchAttributes['feeOrderStatus']);
            })
            ->when($this->searchAttributes['fee_order_id'], function ($q) {
                $feeOrderIds = explode(',', $this->searchAttributes['fee_order_id']);
                $q->whereIn('fo.id', $feeOrderIds);
            })
            ->when($this->searchAttributes['feeBindOrderID'], function ($q) {
                $feeBindOrderID = addslashes($this->searchAttributes['feeBindOrderID']);
                $q->whereRaw(
                    <<<SQL
  CASE fo.order_type
   WHEN 1 then cso.order_id LIKE '%{$feeBindOrderID}%'
   WHEN 2 then yro.order_id LIKE '%{$feeBindOrderID}%'
   WHEN 3 then fo.order_id LIKE '%{$feeBindOrderID}%'
 END
SQL
                );
            })
            ->when($this->searchAttributes['filterOrderId'], function ($q) {
                $q->where(function ($q2){
                    $filterOrderId = addslashes($this->searchAttributes['filterOrderId']);
                    $q2->where('fo.order_no', '=', $filterOrderId)
                        ->orWhereRaw(
                            <<<SQL
  CASE fo.order_type
   WHEN 1 then cso.order_id = '{$filterOrderId}'
   WHEN 2 then yro.order_id = '{$filterOrderId}'
   WHEN 3 then fo.order_id LIKE '%{$filterOrderId}%'
 END
SQL
                        );
                });
            })
            ->groupBy('fo.id');
    }
}
