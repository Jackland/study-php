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
 * 限时限量活动-店铺页面-进行中
 * Class SellerListOnSaleSearch
 * @package App\Catalog\Search\MarketingTimeLimit
 */
class SellerListOnSaleSearch
{
    use SearchModelTrait;

    private $customerId;
    private $countryId;
    private $searchAttributes = [
        'id' => 0,//SellerID
    ];

    public function __construct($customerId, $countryId)
    {
        $this->customerId = $customerId;
        $this->countryId = $countryId;
    }

    /**
     * @param $params
     * @return array
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     * @throws \Exception
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function searchES($params)
    {
        $this->loadAttributes($params);
        $sellerId = $this->searchAttributes['id'];

        $cacheKey = 'MARKETING_TIME_LIMIT_SELLER_LIST_ON_SALE_SELLER_ID_' . $sellerId . '_BUYER_ID_' . $this->customerId;
        if (cache()->has($cacheKey)) {
            return cache()->get($cacheKey);
        }
        $now = Carbon::now()->toDateTimeString();

        $filter_data = [];
        $filter_data['search'] = null;
        $filter_data['category_id'] = 0;
        $filter_data['sort'] = 'marketingDiscount';
        $filter_data['order'] = 'ASC';
        $filter_data['page'] = 1;
        $filter_data['limit'] = 50;
        $filter_data['country'] = CountryHelper::getCountryCodeById($this->countryId);
        $filter_data['min_quantity'] = 1;
        //限时限量活动
        $filter_data['discountPriceBoundMin'] = '';
        $filter_data['discountPriceBoundMax'] = '';
        $filter_data['marketingProductStatus'] = 1;
        $filter_data['marketingQtyMin'] = 1;
        $filter_data['marketingQtyMax'] = '';
        $filter_data['marketingTime'] = str_replace(' ', 'T', $now);
        $filter_data['seller_id'] = $sellerId;
        /** @var \ModelCatalogSearch $ModelCatalogSearch */
        $ModelCatalogSearch = load()->model('catalog/search');
        $tmp = $ModelCatalogSearch->searchRelevanceProductId($filter_data, $this->customerId, SearchParam::SCENE[1], '限时限量活动-店铺-进行中 sellerId=' . $sellerId);
        if (!is_array($tmp) || !array_key_exists('products', $tmp) || !array_key_exists('total', $tmp) || $tmp['total'] < 1) {
            $result = ['total' => 0, 'products' => []];
        } else {
            $result = ['total' => $tmp['total'], 'products' => $tmp['products']];
        }
        cache()->set($cacheKey, $result, 20);
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
            'defaultOrder' => ['discount' => SORT_ASC, 'mp.id' => SORT_ASC],
            'rules' => [
                'discount' => 'mp.discount',
                'mp.id' => 'mp.id',
            ],
        ]));
        $dataProvider->setPaginator(['defaultPageSize' => 9]); // 'pageSizeParam' =>'page_limit_new'
        return $dataProvider;
    }

    protected function buildQuery()
    {
        $sellerId = $this->searchAttributes['id'];
        $now = Carbon::now()->toDateTimeString();
        return MarketingTimeLimit::query()->alias('m')
            ->leftJoinRelations('products as mp')
            ->leftjoin('oc_product as op', 'mp.product_id', '=', 'op.product_id')
            ->where('m.seller_id', '=', $sellerId)
            ->where('m.store_nav_show', '=', YesNoEnum::YES)
            ->where('m.effective_time', '<=', $now)
            ->where('m.expiration_time', '>', $now)
            ->where('m.is_del', '=', YesNoEnum::NO)
            ->where('m.status', '=', 1)
            ->where('mp.qty', '>', 0)
            ->where('mp.status', '=', MarketingTimeLimitProductStatus::NO_RELEASE)
            ->select(['m.id', 'm.transaction_type', 'm.low_qty', 'm.expiration_time', 'm.max_discount'])
            ->addSelect(['mp.product_id', 'mp.discount', 'mp.origin_qty', 'mp.qty', 'op.price'])
            ->selectRaw(new Expression('ROUND(100-mp.discount) AS discountShow'));
    }

    /**
     * 限时限量活动 Store Navigation Bar Menu
     * @param int $sellerId
     * @return bool
     */
    public function isStoreNavShow($sellerId)
    {
        $now = Carbon::now()->toDateTimeString();
        return MarketingTimeLimit::query()->alias('m')
            ->leftJoinRelations(['products as mp'])
            ->where('m.store_nav_show', '=', YesNoEnum::YES)
            ->where('m.effective_time', '<=', $now)
            ->where('m.expiration_time', '>=', $now)
            ->where('m.is_del', '=', YesNoEnum::NO)
            ->where('m.status', '=', 1)
            ->where('mp.qty', '>', 0)
            ->where('mp.status', '=', MarketingTimeLimitProductStatus::NO_RELEASE)
            ->where('m.seller_id', '=', (int)$sellerId)
            ->select(['m.id'])
            ->exists();
    }
}