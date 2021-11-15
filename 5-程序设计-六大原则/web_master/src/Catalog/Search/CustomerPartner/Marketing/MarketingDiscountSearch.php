<?php

namespace App\Catalog\Search\CustomerPartner\Marketing;

use App\Enums\Common\YesNoEnum;
use App\Enums\Marketing\MarketingDiscountBuyerRangeType;
use App\Models\Buyer\BuyerToSeller;
use App\Models\Marketing\MarketingDiscount;
use Carbon\Carbon;
use Framework\DataProvider\QueryDataProvider;
use Framework\DataProvider\SearchModelTrait;
use Framework\DataProvider\Sort;
use Framework\Model\Eloquent\Builder;
use App\Enums\Marketing\MarketingDiscountStatus;

class MarketingDiscountSearch
{
    use SearchModelTrait;

    private $customerId;
    //支持的查询条件
    private $searchAttributes = [
        'filter_keywords' => '',
        'filter_status' => '',
        'event_name' => '',
    ];

    public function __construct($customerId)
    {
        $this->customerId = intval($customerId);
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

        $dataProvider->setSort(new Sort([
            'defaultOrder' => ['discount_current_status' => SORT_ASC, 'update_time' => SORT_DESC],
            'rules' => [
                'discount_current_status' => 'discount_current_status',
                'update_time' => 'a.update_time',
            ],
        ]));

        $dataProvider->setPaginator(['defaultPageSize' => 10]);

        return $dataProvider;
    }

    protected function buildQuery()
    {
        $query = MarketingDiscount::query()->alias('a')
            ->with(['buyers', 'buyers.buyer'])
            ->leftJoin('oc_marketing_discount_buyer as b', 'a.id', '=', 'b.discount_id')
            ->leftJoin('oc_customer as c', 'b.buyer_id', '=', 'c.customer_id')
            ->where('a.seller_id', $this->customerId)
            ->where('a.is_del', YesNoEnum::NO)
            ->select(['a.*']);

        return $this->filterBuild($query);
    }

    /**
     * 组织query
     * @param Builder $query
     * @return Builder
     */
    private function filterBuild(Builder $query)
    {
        //活动名称
        if ($this->searchAttributes['event_name']) {
            $query->where('a.name', 'like', '%' . $this->searchAttributes['event_name'] . '%');
        }
        // Buyer Name或Buyer Number
        if ($this->searchAttributes['filter_keywords']) {
            $existAss = BuyerToSeller::query()->alias('bs')
                ->leftJoin('oc_customer as oer', 'bs.buyer_id', '=', 'oer.customer_id')
                ->where('bs.seller_id', customer()->getId())
                ->where(function ($query3) {
                    $query3->where('oer.nickname', 'like', '%' . $this->searchAttributes['filter_keywords'] . '%')
                        ->orWhere('oer.user_number', 'like', '%' . $this->searchAttributes['filter_keywords'] . '%');
                })
                ->exists();

            $existAss = (int)$existAss;
            $query->where(function ($query3) use ($existAss) {
                $query3->where(function ($query4) use ($existAss) {
                    $query4->where('c.nickname', 'like', '%' . $this->searchAttributes['filter_keywords'] . '%')
                        ->orWhere('c.user_number', 'like', '%' . $this->searchAttributes['filter_keywords'] . '%');
                })->orWhere(function ($query5) use ($existAss) {
                    $query5->where('a.buyer_scope', '=', MarketingDiscountBuyerRangeType::SCOPE_ALL)
                        ->whereRaw(" 1= " . (int)$existAss);
                });
            });
        }

        $currentTime = Carbon::now()->toDateTimeString();

        if (in_array($this->searchAttributes['filter_status'], array_keys(MarketingDiscountStatus::getViewItems()))) {
            switch ($this->searchAttributes['filter_status']) {
                case MarketingDiscountStatus::ACTIVE:
                    $query->where('a.effective_time', '<=', $currentTime);
                    $query->where('a.expiration_time', '>=', $currentTime);
                    break;
                case MarketingDiscountStatus::PENDING:
                    $query->where('a.effective_time', '>', $currentTime);
                    break;
                case MarketingDiscountStatus::INVALID:
                    $query->where('a.expiration_time', '<', $currentTime);
                    break;
            }
        }
        //这坨是产品要求列表按照状态排序，但是数据库没存状态，so额外计算下,4目前是不存在的，容错而已
        $query->selectRaw(
            <<<SQL
                case
                when a.effective_time <= '{$currentTime}' and a.expiration_time  >=  '{$currentTime}' then 1 
                when a.effective_time > '{$currentTime}' then 2 
                when  a.expiration_time  <  '{$currentTime}' then 3
                else 4
                end as discount_current_status
SQL
        );

        return $query->groupBy('a.id');
    }

    protected function loadAttributes($data)
    {
        foreach ($data as $key => $value) {
            if (array_key_exists($key, $this->searchAttributes)) {
                $this->searchAttributes[$key] = trim($value); //过滤前后空格
            }
        }
    }

}
