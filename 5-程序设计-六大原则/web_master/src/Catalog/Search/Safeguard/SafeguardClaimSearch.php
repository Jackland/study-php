<?php

namespace App\Catalog\Search\Safeguard;

use Carbon\Carbon;
use Framework\DataProvider\QueryDataProvider;
use Framework\DataProvider\SearchModelTrait;
use Framework\DataProvider\Sort;
use App\Models\Safeguard\SafeguardClaim;
use App\Enums\Safeguard\BillOrderType;
use App\Enums\Safeguard\SafeguardClaimStatus;
use Illuminate\Database\Eloquent\Builder;
use App\Helper\CountryHelper;

class SafeguardClaimSearch
{
    use SearchModelTrait;

    private $customerId;
    private $searchAttributes = [
        'filter_claim_hot_map' => '',
        'filter_keywords' => '',
        'filter_safeguard_config' => 0,
        'filter_claim_status' => '', //多选  英文,隔开
        'filter_create_time_range_claim' => '',
        'filter_create_time_from' => '',
        'filter_create_time_to' => '',
    ];

    public function __construct($customerId)
    {
        $this->customerId = $customerId;
    }

    public function getStatisticsNumber()
    {
        return $this->buildNumberQuery();
    }

    /**
     * @param $params
     * @param bool $isDownload
     * @throws \Framework\Exception\InvalidConfigException
     * @return Builder | QueryDataProvider
     */
    public function search($params, $isDownload = false)
    {
        $this->loadAttributes($params);
        $query = $this->buildQuery();
        $dataProvider = new QueryDataProvider($query);
        if ($this->searchAttributes['filter_claim_hot_map'] == '' || $this->searchAttributes['filter_claim_hot_map'] == 'all') {
            $defaultOrder = ['create_time' => SORT_DESC];
        } else {
            $defaultOrder = ['last_modified' => SORT_DESC];
        }

        if (!$isDownload) {
            $dataProvider->setSort(new Sort([
                'defaultOrder' => $defaultOrder,
                'rules' => [
                    'last_modified' => 'a.update_time',
                    'create_time' => 'a.create_time',
                ],
            ]));
            $dataProvider->setPaginator(['defaultPageSize' => 10]);
        } else {
            $dataProvider->switchSort(false);
            $dataProvider->switchPaginator(false);
            $query->orderByDesc('a.create_time');
        }

        return $isDownload ? $query : $dataProvider;
    }

    protected function buildQuery()
    {
        $query = SafeguardClaim::query()->alias('a')
            ->with(['claimDetails', 'claimDetails.trackings'])
            ->leftJoin('oc_safeguard_claim_audit as au', 'au.id', '=', 'a.audit_id')
            ->leftJoin('oc_safeguard_claim_detail as b', 'a.id', '=', 'b.claim_id')
            ->leftJoin('oc_safeguard_claim_detail_tracking as f', 'b.id', '=', 'f.claim_detail_id')
            ->leftJoin('oc_safeguard_bill as c', 'a.safeguard_bill_id', '=', 'c.id')
            ->leftJoin('tb_sys_customer_sales_order as e', 'e.id', '=', 'c.order_id')
            ->where('c.order_type', BillOrderType::TYPE_SALES_ORDER)
            ->where('a.buyer_id', $this->customerId);

        $query = $this->filterBuild($query);

        $selectRaw = [
            'a.*',
            'au.create_time as audit_create_time',
            'c.safeguard_no',
            'c.safeguard_config_id',
            'c.safeguard_config_rid',
            'e.id as sale_order_id',
            'e.order_id',
        ];

        return $query->select($selectRaw);
    }

    protected function buildNumberQuery()
    {
        $baseQuery = SafeguardClaim::query()
            ->alias('a')
            ->where('a.buyer_id', $this->customerId);

        $otherQuery = (clone $baseQuery)->where('a.is_viewed', 0);

        $result = [
            'in_progress_number' => (clone $baseQuery)->where('a.status', SafeguardClaimStatus::CLAIM_IN_PROGRESS)->count(),
            'success_number' => (clone $otherQuery)->where('a.status', SafeguardClaimStatus::CLAIM_SUCCEED)->count(),
            'fail_number' => (clone $otherQuery)->where('a.status', SafeguardClaimStatus::CLAIM_FAILED)->count(),
            'backed_number' => (clone $baseQuery)->where('a.status', SafeguardClaimStatus::CLAIM_BACKED)->count(),
        ];

        return $result;
    }

    /**
     * 组织query
     * @param Builder $query
     * @return Builder
     */
    private function filterBuild(Builder $query)
    {
        if ($this->searchAttributes['filter_claim_hot_map'] == '' || $this->searchAttributes['filter_claim_hot_map'] == 'all') {
            // 理赔申请单ID + 关联保单ID + Sales Order ID + Item Code + Tracking Number
            if (!empty($this->searchAttributes['filter_keywords']) || $this->searchAttributes['filter_keywords'] != '') {
                $query->where(function ($q) {
                    $q->where('a.claim_no', '=', $this->searchAttributes['filter_keywords'])
                        ->orWhere('c.safeguard_no', '=', $this->searchAttributes['filter_keywords'])
                        ->orWhere('e.order_id', '=', $this->searchAttributes['filter_keywords'])
                        ->orWhere('b.item_code', '=', $this->searchAttributes['filter_keywords'])
                        ->orWhere('f.tracking_number', '=', $this->searchAttributes['filter_keywords']);
                });
            }

            if ($this->searchAttributes['filter_safeguard_config'] > 0) {
                $query->where('c.safeguard_config_rid', $this->searchAttributes['filter_safeguard_config']);
            }

            if (!empty($this->searchAttributes['filter_claim_status'])) {
                $statusArr = explode(',', $this->searchAttributes['filter_claim_status']);
                if ($statusArr) {
                    $query->whereIn('a.status', $statusArr);
                }
            }

            if (!empty($this->searchAttributes['filter_create_time_from'])) {
                $query->where('a.create_time', '>=', $this->searchAttributes['filter_create_time_from']);
            }

            if (!empty($this->searchAttributes['filter_create_time_to'])) {
                $query->where('a.create_time', '<=', $this->searchAttributes['filter_create_time_to']);
            }

            return $query->groupBy('a.id');
        }

        $mapping = $this->generateMapping();
        $hotMap = $this->searchAttributes['filter_claim_hot_map'];
        if (array_key_exists($hotMap, $mapping)) {
            $query->where('a.status', $mapping[$hotMap]);
        }

        return $query->groupBy('a.id');
    }

    /**
     * 获取文件下载名称
     * @return string
     */
    public function getDownloadFileName()
    {
        $orignFileName = '';
        $timeZone = CountryHelper::getTimezone(customer()->getCountryId());
        if ($this->searchAttributes['filter_create_time_from']) {
            $orignFileName = '_' . Carbon::parse($this->searchAttributes['filter_create_time_from'])->timezone($timeZone)->format('ymd');
        }
        if ($this->searchAttributes['filter_create_time_to']) {
            $orignFileName .= '_' . Carbon::parse($this->searchAttributes['filter_create_time_to'])->timezone($timeZone)->format('ymd');
        }
        return 'Claim Application' . $orignFileName . '.xls';
    }

    protected function loadAttributes($data)
    {
        foreach ($data as $key => $value) {
            if (array_key_exists($key, $this->searchAttributes)) {
                $this->searchAttributes[$key] = trim($value); //过滤前后空格
            }
        }
    }

    //封装个简单映射
    private function generateMapping()
    {
        return [
            'in_process' => SafeguardClaimStatus::CLAIM_IN_PROGRESS,
            'succeed' => SafeguardClaimStatus::CLAIM_SUCCEED,
            'failed' => SafeguardClaimStatus::CLAIM_FAILED,
            'to_be_added' => SafeguardClaimStatus::CLAIM_BACKED,
        ];
    }

}
