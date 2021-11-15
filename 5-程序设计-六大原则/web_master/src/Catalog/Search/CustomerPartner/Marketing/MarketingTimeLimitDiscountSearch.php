<?php

namespace App\Catalog\Search\CustomerPartner\Marketing;

use App\Enums\Common\YesNoEnum;
use App\Enums\Marketing\MarketingTimeLimitStatus;
use App\Models\Marketing\MarketingTimeLimit;
use Carbon\Carbon;
use Framework\DataProvider\QueryDataProvider;
use Framework\DataProvider\SearchModelTrait;
use Framework\DataProvider\Sort;
use Framework\Model\Eloquent\Builder;

class MarketingTimeLimitDiscountSearch
{
    use SearchModelTrait;

    private $customerId;
    //支持的查询条件
    private $searchAttributes = [
        'filter_event_name' => '',
        'filter_status' => '',
    ];

    public function __construct($customerId)
    {
        $this->customerId = intval($customerId);
    }

    /**
     * @param $params
     * @param bool $isDownload
     * @return Builder | QueryDataProvider
     * @throws \Framework\Exception\InvalidConfigException
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
        $query = MarketingTimeLimit::query()->alias('a')
            ->where('a.is_del', YesNoEnum::NO)
            ->where('a.seller_id', $this->customerId)
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
        if ($this->searchAttributes['filter_event_name']) {
            $query->where('a.name', 'like', '%' . $this->searchAttributes['filter_event_name'] . '%');
        }

        $currentTime = Carbon::now()->toDateTimeString();

        if (in_array($this->searchAttributes['filter_status'], array_keys(MarketingTimeLimitStatus::getViewItems()))) {
            switch ($this->searchAttributes['filter_status']) {
                case MarketingTimeLimitStatus::ACTIVE:
                    $query->where('a.effective_time', '<=', $currentTime);
                    $query->where('a.expiration_time', '>=', $currentTime);
                    $query->where('a.status', '!=', MarketingTimeLimitStatus::STOPED);
                    break;
                case MarketingTimeLimitStatus::PENDING:
                    $query->where('a.effective_time', '>', $currentTime);
                    $query->where('a.status', '!=', MarketingTimeLimitStatus::STOPED);
                    break;
                case MarketingTimeLimitStatus::EXPIRED:
                    $query->where('a.expiration_time', '<', $currentTime);
                    $query->where('a.status', '!=', MarketingTimeLimitStatus::STOPED);
                    break;
                case MarketingTimeLimitStatus::STOPED:
                    $query->where('a.status', MarketingTimeLimitStatus::STOPED);
                    break;
            }
        }
        //15目前是不存在的，容错
        $query->selectRaw(
            <<<SQL
                case
                when a.status = 10  then 10
                when a.effective_time <= '{$currentTime}' and a.expiration_time  >=  '{$currentTime}' then 1
                when a.effective_time > '{$currentTime}' then 2
                when  a.expiration_time  <  '{$currentTime}' then 3
                else 15
                end as discount_current_status
SQL
        );

        return $query;
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
