<?php

namespace App\Catalog\Search\Safeguard;

use Framework\DataProvider\QueryDataProvider;
use Framework\DataProvider\SearchModelTrait;
use Framework\DataProvider\Sort;
use App\Models\Safeguard\SafeguardBill;
use Framework\Model\Eloquent\Builder;
use App\Enums\Safeguard\SafeguardBillStatus;
use App\Enums\Safeguard\BillOrderType;

class SafeguardBillSearch
{
    use SearchModelTrait;

    private $customerId;
    private $searchAttributes = [
        'filter_no' => '',//保单Id或者Sales order id
        'filter_title' => '',
        'filter_status' => '',
        'filter_create_time_range' => '',
        'filter_creation_date_from' => '',
        'filter_creation_date_to' => '',
        'filter_tab' => '',
        'filter_tab_status' => '',
    ];
    public $defaultPageSize = 10;
    public $currentTime;

    public function __construct($customerId)
    {
        $this->customerId = $customerId;
        $this->currentTime = date('Y-m-d H:i:s', time());
    }

    public function search($params, $isDownload = false)
    {

        if (isset($params['filter_tab']) && trim($params['filter_tab'])) {
            $params['filter_tab_status'] = $this->tabShowStatus()[trim($params['filter_tab'])];
        }

        $this->loadAttributes($params);

        $query = $this->buildQuery();
        $dataProvider = new QueryDataProvider($query);

        $dataProvider->setSort(new Sort([
            'defaultOrder' => ['create_time' => SORT_DESC],
            'alwaysOrder' => ['id' => SORT_ASC],
            'rules' => [
                'create_time' => 'sb.create_time',
                'expiration_time' => 'sb.expiration_time',
                'effective_time' => 'sb.effective_time',
                'id' => 'sb.id',
            ],
        ]));
        if (!$isDownload) {
            $dataProvider->setPaginator(['defaultPageSize' => $this->defaultPageSize]);
        } else {
            $dataProvider->switchPaginator(false);
        }
        return $dataProvider;
    }

    protected function buildQuery()
    {
        return SafeguardBill::query()->alias('sb')
            ->with(['safeguardClaim'])
            ->select(['sb.*'])
            ->addSelect('os.title', 'cso.order_id as sales_order_no', 'cso.order_status', 'fosd.safeguard_fee', 'fo.order_no')
            ->leftJoin('oc_safeguard_config AS os', 'os.id', '=', 'sb.safeguard_config_id')
            ->leftJoin('tb_sys_customer_sales_order AS cso', 'cso.id', '=', 'sb.order_id')
            ->leftJoin('oc_fee_order_safeguard_detail AS fosd', 'fosd.safeguard_bill_id', '=', 'sb.id')
            ->leftJoin('oc_fee_order AS fo', 'fosd.fee_order_id', '=', 'fo.id')
            ->where('sb.buyer_id', '=', $this->customerId)
            ->where('sb.order_type', '=', BillOrderType::TYPE_SALES_ORDER)//目前只有销售订单
            ->when($this->searchAttributes['filter_no'] != '', function (Builder $q) {
                $q->where(function ($query) {
                    $query->where('sb.safeguard_no', '=', $this->searchAttributes['filter_no'])
                        ->orWhere('cso.order_id', '=', $this->searchAttributes['filter_no']);
                });
            })->when($this->searchAttributes['filter_title'] != '', function (Builder $q) {
                $q->where('sb.safeguard_config_rid', '=', $this->searchAttributes['filter_title']);
            })->when($this->searchAttributes['filter_status'] == SafeguardBillStatus::ACTIVE || $this->searchAttributes['filter_tab_status'] == SafeguardBillStatus::ACTIVE, function (Builder $q) { //保障中
                $q->where(function ($query) {
                    $query->where('sb.effective_time', '<=', $this->currentTime)
                        ->where('sb.expiration_time', '>=', $this->currentTime)
                        ->where('sb.status', '=', SafeguardBillStatus::ACTIVE);
                });
            })->when($this->searchAttributes['filter_status'] == SafeguardBillStatus::CANCELED || $this->searchAttributes['filter_tab_status'] == SafeguardBillStatus::CANCELED, function (Builder $q) { //已取消
                $q->where('sb.status', '=', SafeguardBillStatus::CANCELED);
            })->when($this->searchAttributes['filter_status'] == SafeguardBillStatus::PENDING || $this->searchAttributes['filter_tab_status'] == SafeguardBillStatus::PENDING, function (Builder $q) { //待生效
                $q->where('sb.status', '=', SafeguardBillStatus::PENDING);
            })->when($this->searchAttributes['filter_status'] == SafeguardBillStatus::INVALID || $this->searchAttributes['filter_tab_status'] == SafeguardBillStatus::INVALID, function (Builder $q) { //已失效
                $q->where(function ($query) {
                    $query->where('sb.expiration_time', '<', $this->currentTime)
                        ->where('sb.status', '=', SafeguardBillStatus::ACTIVE);
                });
            })->when($this->searchAttributes['filter_creation_date_from'] != '', function (Builder $q) {
                $q->where('sb.create_time', '>=', $this->searchAttributes['filter_creation_date_from']);
            })->when($this->searchAttributes['filter_creation_date_to'] != '', function (Builder $q) {
                $q->where('sb.create_time', '<=', $this->searchAttributes['filter_creation_date_to']);
            });
    }

    /**
     * 获取每个tab的数量
     * @param array $params 页面查询参数
     * @return array $params
     *
     */
    public function getBillStatusCount(array $params)
    {
        $tabs = $this->tabShowStatus();
        $result = [];
        foreach ($tabs as $key => $tab) {
            $params['filter_tab_status'] = $tab;
            $this->loadAttributes($params);
            $count = $this->buildQuery()->count();
            $result[$key] = $count < 1000 ? $count : '999';
        }
        return $result;
    }

    /**
     * 页面tab对应搜索状态值
     * @return array
     */
    public function tabShowStatus()
    {
        return [
            'all' => '',
            'active' => SafeguardBillStatus::ACTIVE,
            'pending' => SafeguardBillStatus::PENDING,
            'invalid' => SafeguardBillStatus::INVALID,
            'canceled' => SafeguardBillStatus::CANCELED,
        ];
    }
}

