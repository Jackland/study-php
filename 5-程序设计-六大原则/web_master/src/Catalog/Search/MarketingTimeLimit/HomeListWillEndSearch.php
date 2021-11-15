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
 * 限时限量活动-首页频道-即将结束
 * Class MarketingTimeLimitListWillEndSearch
 * @package App\Catalog\Search\MarketingTimeLimit
 */
class HomeListWillEndSearch
{
    use SearchModelTrait;

    private $customerId;
    private $countryId;
    private $searchAttributes = [
        'willEndActiveIds' => '',
    ];

    public function __construct($customerId, $countryId)
    {
        $this->customerId = $customerId;
        $this->countryId = $countryId;
    }

    /**
     * 即将结束
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
        $cacheKey = 'MARKETING_TIME_LIMIT_HOME_LIST_WILL_END_BUYER_ID_' . $this->customerId;
        if ($page == 1 && cache()->has($cacheKey)) {
            return cache()->get($cacheKey);
        }

        $pointAfter = Carbon::now()->addHours(2)->format('Y-m-d\TH:i:s');//Java那边要求这个参数中间要有一个字母T

        $filter_data = [];
        $filter_data['search'] = null;
        $filter_data['category_id'] = 0;
        $filter_data['sort'] = 'marketingExpirationTime';
        $filter_data['order'] = 'ASC';
        $filter_data['page'] = (int)request('page', 1);
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
        $filter_data['marketingTimeEnd'] = $pointAfter;
        /** @var \ModelCatalogSearch $ModelCatalogSearch */
        $ModelCatalogSearch = load()->model('catalog/search');
        $tmp = $ModelCatalogSearch->searchRelevanceProductId($filter_data, $this->customerId, SearchParam::SCENE[1], '限时限量活动 即将结束列表');
        if (is_array($tmp) and array_key_exists('products', $tmp) and array_key_exists('total', $tmp) && $tmp['total'] > 0) {
            $result = ['total' => $tmp['total'], 'products' => $tmp['products'], 'allProductIds' => $tmp['allProductIds']];
        } else {
            $result = ['total' => 0, 'products' => [], 'allProductIds' => []];
        }
        if ($page == 1) {
            cache()->set($cacheKey, $result, 20);
        }
        return $result;
    }

    /**
     * @param $params
     * @return QueryDataProvider
     * @throws \Framework\Exception\InvalidConfigException
     */
    public function search($params)
    {
        $this->loadAttributes($params);
        $query = $this->buildQuery();
        $dataProvider = new QueryDataProvider($query);
        $dataProvider->setSort(new Sort([
            'enableMultiple' => true,
            'defaultOrder' => ['discount' => SORT_ASC, 'm.effective_time' => SORT_ASC, 'mp.id' => SORT_ASC],
            'rules' => [
                'discount' => 'mp.discount',
                'm.effective_time' => 'm.effective_time',
                'mp.id' => 'mp.id'
            ],
        ]));
        $dataProvider->setPaginator(['defaultPageSize' => 9]); // 'pageSizeParam' =>'page_limit_new'
        return $dataProvider;
    }

    protected function buildQuery()
    {
        $willEndActiveIdArr = explode(',', $this->searchAttributes['willEndActiveIds']);
        return MarketingTimeLimit::query()->alias('m')
            ->leftJoinRelations(['products as mp'])
            ->leftjoin('oc_customer as seller', 'm.seller_id', '=', 'seller.customer_id')
            ->leftjoin('oc_product as op', 'mp.product_id', '=', 'op.product_id')
            ->whereIn('m.id', $willEndActiveIdArr)
            ->where('m.is_del', '=', YesNoEnum::NO)
            ->where('m.status', '=', 1)
            ->where('mp.qty', '>', 0)
            ->where('mp.status', '=', MarketingTimeLimitProductStatus::NO_RELEASE)
            ->where('seller.country_id', '=', $this->countryId)
            ->groupBy('mp.product_id')
            ->select(['m.id', 'mp.product_id', 'mp.discount', 'mp.qty', 'op.price'])
            ->selectRaw(new Expression('ROUND(100-mp.discount) AS discountShow'));
    }

    /**
     * 即将结束
     * @return array
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     * @throws \Exception
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function checkWillEnd()
    {
        $isWillEnd = false;
        $willEndIntervalHour = '';
        $willEndIntervalMinute = '';
        $willEndIntervalSecond = '';
        $total = 0;
        $products = [];

        $now = Carbon::now()->toDateTimeString();
        $result = $this->searchES();
        $total = $result['total'];
        $products = $result['products'];
        if ($products) {
            $isWillEnd = true;
            $tmp = reset($products);
            $expirationTimeString = $tmp['marketingExpirationTime'];
            $origin = new \DateTime($expirationTimeString);
            $target = new \DateTime($now);
            $interval = $origin->diff($target);
            $willEndIntervalHour = $interval->format('%H');
            $willEndIntervalMinute = $interval->format('%I');
            $willEndIntervalSecond = $interval->format('%S');
        }

        return [
            'isWillEnd' => $isWillEnd,
            'willEndIntervalHour' => $willEndIntervalHour,
            'willEndIntervalMinute' => $willEndIntervalMinute,
            'willEndIntervalSecond' => $willEndIntervalSecond,
            'total' => $total,
            'products' => $products,
        ];
    }
}
