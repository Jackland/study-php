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
use Illuminate\Database\Query\Expression;

/**
 * 限时限量活动-首页频道-实时热抢
 * Class MarketingTimeLimitListHotSearch
 * @package App\Catalog\Search\MarketingTimeLimit
 */
class HomeListHotSearch
{
    use SearchModelTrait;

    private $customerId;
    private $countryId;
    private $searchAttributes = [
    ];

    public function __construct($customerId, $countryId)
    {
        $this->customerId = $customerId;
        $this->countryId = $countryId;
    }

    /**
     * @return array
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     * @throws \Exception
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function searchES()
    {
        $page = (int)request('page', 1);
        $cacheKey = 'MARKETING_TIME_LIMIT_HOME_LIST_HOT_BUYER_ID_' . $this->customerId;
        if ($page == 1 && cache()->has($cacheKey)) {
            return cache()->get($cacheKey);
        }
        $pointBefore = Carbon::now()->subHours(2)->format('Y-m-d\TH:i:s');//Java那边要求这个参数中间要有一个字母T

        $filter_data = [];
        $filter_data['search'] = null;
        $filter_data['category_id'] = 0;
        $filter_data['sort'] = 'marketingDiscount';
        $filter_data['order'] = 'ASC';
        $filter_data['page'] = $page;
        $filter_data['limit'] = (int)request('page_limit', 9);
        $filter_data['country'] = CountryHelper::getCountryCodeById($this->countryId);
        $filter_data['min_quantity'] = 1;
        //限时限量活动
        $filter_data['discountPriceBoundMin'] = '';
        $filter_data['discountPriceBoundMax'] = '';
        $filter_data['marketingProductStatus'] = 1;
        $filter_data['marketingQtyMin'] = 1;
        $filter_data['marketingQtyMax'] = '';
        $filter_data['marketingTimeRange'] = 2;
        $filter_data['marketingTimeStart'] = $pointBefore;
        /** @var \ModelCatalogSearch $ModelCatalogSearch */
        $ModelCatalogSearch = load()->model('catalog/search');
        $tmp = $ModelCatalogSearch->searchRelevanceProductId($filter_data, $this->customerId, SearchParam::SCENE[1], '限时限量活动 实时热抢列表');
        if (!is_array($tmp) || !array_key_exists('products', $tmp) || !array_key_exists('total', $tmp) || $tmp['total'] < 1) {
            $result = ['total' => 0, 'products' => []];
        } else {
            $result = ['total' => $tmp['total'], 'products' => $tmp['products']];
        }
        if ($page == 1) {
            cache()->set($cacheKey, $result, 20);
        }
        return $result;
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
            'defaultOrder' => ['discount' => SORT_ASC, 'm.effective_time' => SORT_ASC, 'mp.id' => SORT_ASC],
            'rules' => [
                'discount' => 'mp.discount',
                'm.effective_time' => 'm.effective_time',
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
            ->leftjoin('oc_product as op', 'mp.product_id', '=', 'op.product_id')
            ->where('m.effective_time', '>=', $pointBefore)
            ->where('m.effective_time', '<=', $now)
            ->where('m.expiration_time', '>=', $now)
            ->where('m.is_del', '=', YesNoEnum::NO)
            ->where('m.status', '=', 1)
            ->where('mp.qty', '>', 0)
            ->where('mp.status', '=', MarketingTimeLimitProductStatus::NO_RELEASE)
            ->where('seller.country_id', '=', $this->countryId)
            ->groupBy('mp.product_id')
            ->select(['m.id', 'mp.product_id', 'mp.discount', 'mp.qty', 'op.price'])
            ->selectRaw(new Expression('ROUND(100-mp.discount) AS discountShow'));
    }
}
