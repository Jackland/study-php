<?php

namespace App\Catalog\Search\MarketingTimeLimit;

use App\Enums\Common\YesNoEnum;
use App\Enums\Marketing\MarketingTimeLimitProductStatus;
use App\Helper\CountryHelper;
use App\Models\Marketing\MarketingTimeLimit;
use App\Repositories\Product\Search\SearchParam;
use Carbon\Carbon;
use Framework\DataProvider\QueryDataProvider;
use Framework\DataProvider\SearchModelTrait;
use Framework\DataProvider\Sort;

/**
 * 限时限量活动-首页频道-进行中
 * Class HomeListOnSaleSearch
 * @package App\Catalog\Search\MarketingTimeLimit
 */
class HomeListOnSaleSearch
{
    use SearchModelTrait;

    private $customerId;
    private $countryId;

    public function __construct($customerId, $countryId)
    {
        $this->customerId = $customerId;
        $this->countryId = $countryId;
    }

    /**
     * @return QueryDataProvider
     * @throws \Framework\Exception\InvalidConfigException
     */
    public function search()
    {
        $query = $this->buildQuery();
        $dataProvider = new QueryDataProvider($query);
        $dataProvider->setSort(new Sort([
            'enableMultiple' => true,
            'defaultOrder' => ['effective_time' => SORT_ASC, 'discount' => SORT_ASC, 'mp.id' => SORT_ASC],
            'rules' => [
                'effective_time' => 'm.effective_time',
                'discount' => 'mp.discount',
                'mp.id' => 'mp.id',
            ],
        ]));
        $dataProvider->setPaginator(['defaultPageSize' => 9]); // 'pageSizeParam' =>'page_limit_new'
        return $dataProvider;
    }

    protected function buildQuery()
    {
        $now = Carbon::now()->toDateTimeString();
        $pointBefore = Carbon::now()->subHours(2)->toDateTimeString();

        return MarketingTimeLimit::query()->alias('m')
            ->leftJoinRelations(['products as mp'])
            ->leftjoin('oc_customer as seller', 'm.seller_id', '=', 'seller.customer_id')
            ->where('m.effective_time', '>=', $pointBefore)
            ->where('m.effective_time', '<=', $now)
            ->where('m.expiration_time', '>=', $now)
            ->where('m.is_del', '=', YesNoEnum::NO)
            ->where('m.status', '=', 1)
            ->where('mp.qty', '>', 0)
            ->where('mp.status', '=', MarketingTimeLimitProductStatus::NO_RELEASE)
            ->where('seller.country_id', '=', $this->countryId);
    }
}