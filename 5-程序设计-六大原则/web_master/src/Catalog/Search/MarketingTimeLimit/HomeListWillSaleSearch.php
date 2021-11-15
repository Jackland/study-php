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
 * 限时限量活动-首页频道-即将开始
 * Class MarketingTimeLimitListWillSaleSearch
 * @package App\Catalog\Search\MarketingTimeLimit
 */
class HomeListWillSaleSearch
{
    use SearchModelTrait;

    private $customerId;
    private $countryId;
    private $searchAttributes = [
        'intervalSession' => '',
        'interval' => '',
        'sellerId' => '',
        'effective_time_before' => '',
        'effective_time_after' => '',
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
     */
    public function searchESForSellerId($params)
    {
        $this->loadAttributes($params);

        $start = str_replace(' ', 'T', $this->searchAttributes['effective_time_before']);//Y-m-d H:i:s
        $end = str_replace(' ', 'T', $this->searchAttributes['effective_time_after']);  //Y-m-d H:i:s
        $sellerId = $this->searchAttributes['sellerId'];

        $filter_data = [];
        $filter_data['search'] = null;
        $filter_data['category_id'] = 0;
        $filter_data['sort'] = 'marketingDiscount';
        $filter_data['order'] = 'ASC';
        $filter_data['page'] = 1;
        $filter_data['limit'] = 100;
        $filter_data['country'] = CountryHelper::getCountryCodeById($this->countryId);
        //限时限量活动
        $filter_data['discountPriceBoundMin'] = '';
        $filter_data['discountPriceBoundMax'] = '';
        $filter_data['marketingProductStatus'] = 1;
        $filter_data['marketingQtyMin'] = 1;
        $filter_data['marketingQtyMax'] = '';
        $filter_data['marketingTimeStart'] = str_replace(' ', 'T', $start);//Java那边要求这个参数中间要有一个字母T
        $filter_data['marketingTimeEnd'] = str_replace(' ', 'T', $end);
        $filter_data['seller_id'] = $sellerId;
        /** @var \ModelCatalogSearch $ModelCatalogSearch */
        $ModelCatalogSearch = load()->model('catalog/search');
        $tmp = $ModelCatalogSearch->searchRelevanceProductId($filter_data, $this->customerId, SearchParam::SCENE[1], '限时限量活动 即将开始 时间区间列表 ' . $this->searchAttributes['interval'] . ' sellerId=' . $sellerId);
        if (!is_array($tmp) || !array_key_exists('products', $tmp) || !array_key_exists('total', $tmp) || $tmp['total'] < 1) {
            return ['total' => 0, 'products' => []];
        } else {
            $effectiveTimeMin = '';
            $activeIdMin = 0;
            $activeId2Arr = [];
            foreach ($tmp['products'] as $value) {
                $activeId = $value['marketingTimeLimitId'];
                if ($effectiveTimeMin == '' || $value['marketingEffectiveTime'] < $effectiveTimeMin) {
                    $effectiveTimeMin = $value['marketingEffectiveTime'];
                    $activeIdMin = $activeId;
                }
                $activeId2Arr[$activeId][] = $value;
            }
            return ['total' => count($activeId2Arr[$activeIdMin]), 'products' => $activeId2Arr[$activeIdMin]];
        }
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
            'defaultOrder' => ['effective_time' => SORT_ASC, 'discount' => SORT_ASC, 'mp.id' => SORT_ASC],
            'rules' => [
                'effective_time' => 'm.effective_time',
                'discount' => 'mp.discount',
                'mp.id' => 'mp.id',
            ],
        ]));
        $dataProvider->setPaginator(['defaultPageSize' => 16]); // 'pageSizeParam' =>'page_limit_new'
        return $dataProvider;
    }

    protected function buildQuery()
    {
        $intervalArr = explode('_', $this->searchAttributes['interval']);
        $start = $intervalArr[0];//Y-m-d H:i:s
        $end = $intervalArr[1];  //Y-m-d H:i:s

        return MarketingTimeLimit::query()->alias('m')
            ->leftJoinRelations(['products as mp'])
            ->leftjoin('oc_customer as seller', 'm.seller_id', '=', 'seller.customer_id')
            ->leftJoin('oc_customerpartner_to_customer as c2c', 'm.seller_id', '=', 'c2c.customer_id')
            ->where('m.pre_hot', '=', YesNoEnum::YES)
            ->where('m.effective_time', '>=', $start)
            ->where('m.effective_time', '<', $end)
            ->where('m.is_del', '=', YesNoEnum::NO)
            ->where('m.status', '=', 1)
            ->where('mp.qty', '>', 0)
            ->where('mp.status', '=', MarketingTimeLimitProductStatus::NO_RELEASE)
            ->where('seller.country_id', '=', $this->countryId)
            ->groupBy('m.id')
            ->select(['m.id', 'm.seller_id', 'm.effective_time', 'm.max_discount', 'm.max_discount as max_discount_show', 'c2c.screenname']);
    }

    /**
     * 即将开始 时间区间列表
     * @param int $countryId
     * @param int $buyerId
     * @return array ['00:00 - 08:00'=>[], '08:00 - 12:00'=>[], '12:00 - 18:00'=>[], '18:00 - 00:00'=>[]]
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     * @throws \Exception
     */
    public function getListWillSaleInterval($countryId, $buyerId)
    {
        //根据当前时间，确定 时间区间的排序。默认：//00:00~08:00 08:00~12:00 12:00~18:00 18:00~24:00
        $listInterval = [];

        $serverTimezone = CountryHelper::getTimezoneByCode('USA');
        $localTimezone = CountryHelper::getTimezone($countryId);
        $now = Carbon::now()->toDateTimeString();

        //转换成对应国别的当地时间
        $intervalLocalNow = Carbon::parse($now, $serverTimezone)->setTimezone($localTimezone)->toDateTimeString();
        $intervalLocal00 = Carbon::parse($now, $serverTimezone)->setTimezone($localTimezone)->format('Y-m-d 00:00:00');
        $intervalLocal08 = Carbon::parse($now, $serverTimezone)->setTimezone($localTimezone)->format('Y-m-d 08:00:00');
        $intervalLocal12 = Carbon::parse($now, $serverTimezone)->setTimezone($localTimezone)->format('Y-m-d 12:00:00');
        $intervalLocal18 = Carbon::parse($now, $serverTimezone)->setTimezone($localTimezone)->format('Y-m-d 18:00:00');
        $intervalLocal24 = Carbon::parse($now, $serverTimezone)->setTimezone($localTimezone)->addDays(1)->format('Y-m-d 00:00:00');

        //转换成美国太平洋时间
        $intervalServerNow = Carbon::parse($intervalLocalNow, $localTimezone)->setTimezone($serverTimezone)->toDateTimeString();
        $intervalServer08 = Carbon::parse($intervalLocal08, $localTimezone)->setTimezone($serverTimezone)->toDateTimeString();
        $intervalServer12 = Carbon::parse($intervalLocal12, $localTimezone)->setTimezone($serverTimezone)->toDateTimeString();
        $intervalServer18 = Carbon::parse($intervalLocal18, $localTimezone)->setTimezone($serverTimezone)->toDateTimeString();
        $intervalServer24 = Carbon::parse($intervalLocal24, $localTimezone)->setTimezone($serverTimezone)->toDateTimeString();

        $nowLocalHour = Carbon::parse($intervalLocalNow, $localTimezone)->format('H');
        $localHour00 = Carbon::parse($intervalLocal00, $localTimezone)->format('H');
        $localHour08 = Carbon::parse($intervalLocal08, $localTimezone)->format('H');
        $localHour12 = Carbon::parse($intervalLocal12, $localTimezone)->format('H');
        $localHour18 = Carbon::parse($intervalLocal18, $localTimezone)->format('H');

        $searchInterval = [
            '00:00 - 08:00' => false,
            '08:00 - 12:00' => false,
            '12:00 - 18:00' => false,
            '18:00 - 24:00' => false,
        ];

        $filter_data = [];
        $filter_data['search'] = null;
        $filter_data['category_id'] = 0;
        $filter_data['sort'] = 'marketingDiscount';
        $filter_data['order'] = 'ASC';
        $filter_data['page'] = 1;
        $filter_data['limit'] = 1;
        $filter_data['country'] = CountryHelper::getCountryCodeById($countryId);
        //限时限量活动
        $filter_data['discountPriceBoundMin'] = '';
        $filter_data['discountPriceBoundMax'] = '';
        $filter_data['marketingProductStatus'] = 1;
        $filter_data['marketingQtyMin'] = 1;
        $filter_data['marketingQtyMax'] = '';
        $filter_data['marketingPreHot'] = 1;
        /** @var \ModelCatalogSearch $ModelCatalogSearch */
        $ModelCatalogSearch = load()->model('catalog/search');

        if ($localHour00 <= $nowLocalHour && $nowLocalHour < $localHour08) {
            $intervalServer08Reset = Carbon::parse($intervalServer08, $serverTimezone)->subSeconds(1)->toDateTimeString();
            $intervalServer12Reset = Carbon::parse($intervalServer12, $serverTimezone)->subSeconds(1)->toDateTimeString();
            $intervalServer18Reset = Carbon::parse($intervalServer18, $serverTimezone)->subSeconds(1)->toDateTimeString();
            $listInterval = [
                '00:00 - 08:00' => $intervalServerNow . '_' . $intervalServer08,
                '08:00 - 12:00' => $intervalServer08 . '_' . $intervalServer12,
                '12:00 - 18:00' => $intervalServer12 . '_' . $intervalServer18,
                '18:00 - 24:00' => $intervalServer18 . '_' . $intervalServer24,
            ];

            $filter_data['marketingTimeStart'] = str_replace(' ', 'T', $intervalServerNow);
            $filter_data['marketingTimeEnd'] = str_replace(' ', 'T', $intervalServer08);
            $tmp = $ModelCatalogSearch->searchRelevanceProductId($filter_data, $buyerId, SearchParam::SCENE[1], '限时限量活动 即将开始 时间区间列表 ' . $intervalServerNow . '_' . $intervalServer08);
            if (is_array($tmp) && array_key_exists('products', $tmp) && array_key_exists('total', $tmp) && $tmp['total'] > 0) {
                $searchInterval['00:00 - 08:00'] = true;
            }
            $filter_data['marketingTimeStart'] = str_replace(' ', 'T', $intervalServer08Reset);
            $filter_data['marketingTimeEnd'] = str_replace(' ', 'T', $intervalServer12);
            $tmp = $ModelCatalogSearch->searchRelevanceProductId($filter_data, $buyerId, SearchParam::SCENE[1], '限时限量活动 即将开始 时间区间列表 ' . $intervalServer08Reset . '_' . $intervalServer12);
            if (is_array($tmp) && array_key_exists('products', $tmp) && array_key_exists('total', $tmp) && $tmp['total'] > 0) {
                $searchInterval['08:00 - 12:00'] = true;
            }
            $filter_data['marketingTimeStart'] = str_replace(' ', 'T', $intervalServer12Reset);
            $filter_data['marketingTimeEnd'] = str_replace(' ', 'T', $intervalServer18);
            $tmp = $ModelCatalogSearch->searchRelevanceProductId($filter_data, $buyerId, SearchParam::SCENE[1], '限时限量活动 即将开始 时间区间列表 ' . $intervalServer12Reset . '_' . $intervalServer18);
            if (is_array($tmp) && array_key_exists('products', $tmp) && array_key_exists('total', $tmp) && $tmp['total'] > 0) {
                $searchInterval['12:00 - 18:00'] = true;
            }
            $filter_data['marketingTimeStart'] = str_replace(' ', 'T', $intervalServer18Reset);
            $filter_data['marketingTimeEnd'] = str_replace(' ', 'T', $intervalServer24);
            $tmp = $ModelCatalogSearch->searchRelevanceProductId($filter_data, $buyerId, SearchParam::SCENE[1], '限时限量活动 即将开始 时间区间列表 ' . $intervalServer18Reset . '_' . $intervalServer24);
            if (is_array($tmp) && array_key_exists('products', $tmp) && array_key_exists('total', $tmp) && $tmp['total'] > 0) {
                $searchInterval['18:00 - 24:00'] = true;
            }
        } elseif ($localHour08 <= $nowLocalHour && $nowLocalHour < $localHour12) {
            $intervalServer08 = Carbon::parse($intervalServer08, $serverTimezone)->addDays(1)->toDateTimeString();

            $intervalServer12Reset = Carbon::parse($intervalServer12, $serverTimezone)->subSeconds(1)->toDateTimeString();
            $intervalServer18Reset = Carbon::parse($intervalServer18, $serverTimezone)->subSeconds(1)->toDateTimeString();
            $intervalServer24Reset = Carbon::parse($intervalServer24, $serverTimezone)->subSeconds(1)->toDateTimeString();
            $listInterval = [
                '08:00 - 12:00' => $intervalServerNow . '_' . $intervalServer12,
                '12:00 - 18:00' => $intervalServer12 . '_' . $intervalServer18,
                '18:00 - 24:00' => $intervalServer18 . '_' . $intervalServer24,
                '00:00 - 08:00' => $intervalServer24 . '_' . $intervalServer08,
            ];

            $filter_data['marketingTimeStart'] = str_replace(' ', 'T', $intervalServerNow);
            $filter_data['marketingTimeEnd'] = str_replace(' ', 'T', $intervalServer12);
            $tmp = $ModelCatalogSearch->searchRelevanceProductId($filter_data, $buyerId, SearchParam::SCENE[1], '限时限量活动 即将开始 时间区间列表 ' . $intervalServerNow . '_' . $intervalServer12);
            if (is_array($tmp) && array_key_exists('products', $tmp) && array_key_exists('total', $tmp) && $tmp['total'] > 0) {
                $searchInterval['08:00 - 12:00'] = true;
            }
            $filter_data['marketingTimeStart'] = str_replace(' ', 'T', $intervalServer12Reset);
            $filter_data['marketingTimeEnd'] = str_replace(' ', 'T', $intervalServer18);
            $tmp = $ModelCatalogSearch->searchRelevanceProductId($filter_data, $buyerId, SearchParam::SCENE[1], '限时限量活动 即将开始 时间区间列表 ' . $intervalServer12Reset . '_' . $intervalServer18);
            if (is_array($tmp) && array_key_exists('products', $tmp) && array_key_exists('total', $tmp) && $tmp['total'] > 0) {
                $searchInterval['12:00 - 18:00'] = true;
            }
            $filter_data['marketingTimeStart'] = str_replace(' ', 'T', $intervalServer18Reset);
            $filter_data['marketingTimeEnd'] = str_replace(' ', 'T', $intervalServer24);
            $tmp = $ModelCatalogSearch->searchRelevanceProductId($filter_data, $buyerId, SearchParam::SCENE[1], '限时限量活动 即将开始 时间区间列表 ' . $intervalServer18Reset . '_' . $intervalServer24);
            if (is_array($tmp) && array_key_exists('products', $tmp) && array_key_exists('total', $tmp) && $tmp['total'] > 0) {
                $searchInterval['18:00 - 24:00'] = true;
            }
            $filter_data['marketingTimeStart'] = str_replace(' ', 'T', $intervalServer24Reset);
            $filter_data['marketingTimeEnd'] = str_replace(' ', 'T', $intervalServer08);
            $tmp = $ModelCatalogSearch->searchRelevanceProductId($filter_data, $buyerId, SearchParam::SCENE[1], '限时限量活动 即将开始 时间区间列表 ' . $intervalServer24Reset . '_' . $intervalServer08);
            if (is_array($tmp) && array_key_exists('products', $tmp) && array_key_exists('total', $tmp) && $tmp['total'] > 0) {
                $searchInterval['00:00 - 08:00'] = true;
            }
        } elseif ($localHour12 <= $nowLocalHour && $nowLocalHour < $localHour18) {
            $intervalServer08 = Carbon::parse($intervalServer08, $serverTimezone)->addDays(1)->toDateTimeString();
            $intervalServer12 = Carbon::parse($intervalServer12, $serverTimezone)->addDays(1)->toDateTimeString();

            $intervalServer18Reset = Carbon::parse($intervalServer18, $serverTimezone)->subSeconds(1)->toDateTimeString();
            $intervalServer24Reset = Carbon::parse($intervalServer24, $serverTimezone)->subSeconds(1)->toDateTimeString();
            $intervalServer08Reset = Carbon::parse($intervalServer08, $serverTimezone)->subSeconds(1)->toDateTimeString();
            $listInterval = [
                '12:00 - 18:00' => $intervalServerNow . '_' . $intervalServer18,
                '18:00 - 24:00' => $intervalServer18 . '_' . $intervalServer24,
                '00:00 - 08:00' => $intervalServer24 . '_' . $intervalServer08,
                '08:00 - 12:00' => $intervalServer08 . '_' . $intervalServer12,
            ];

            $filter_data['marketingTimeStart'] = str_replace(' ', 'T', $intervalServerNow);
            $filter_data['marketingTimeEnd'] = str_replace(' ', 'T', $intervalServer18);
            $tmp = $ModelCatalogSearch->searchRelevanceProductId($filter_data, $buyerId, SearchParam::SCENE[1], '限时限量活动 即将开始 时间区间列表 ' . $intervalServerNow . '_' . $intervalServer18);
            if (is_array($tmp) && array_key_exists('products', $tmp) && array_key_exists('total', $tmp) && $tmp['total'] > 0) {
                $searchInterval['12:00 - 18:00'] = true;
            }
            $filter_data['marketingTimeStart'] = str_replace(' ', 'T', $intervalServer18Reset);
            $filter_data['marketingTimeEnd'] = str_replace(' ', 'T', $intervalServer24);
            $tmp = $ModelCatalogSearch->searchRelevanceProductId($filter_data, $buyerId, SearchParam::SCENE[1], '限时限量活动 即将开始 时间区间列表 ' . $intervalServer18Reset . '_' . $intervalServer24);
            if (is_array($tmp) && array_key_exists('products', $tmp) && array_key_exists('total', $tmp) && $tmp['total'] > 0) {
                $searchInterval['18:00 - 24:00'] = true;
            }
            $filter_data['marketingTimeStart'] = str_replace(' ', 'T', $intervalServer24Reset);
            $filter_data['marketingTimeEnd'] = str_replace(' ', 'T', $intervalServer08);
            $tmp = $ModelCatalogSearch->searchRelevanceProductId($filter_data, $buyerId, SearchParam::SCENE[1], '限时限量活动 即将开始 时间区间列表 ' . $intervalServer24Reset . '_' . $intervalServer08);
            if (is_array($tmp) && array_key_exists('products', $tmp) && array_key_exists('total', $tmp) && $tmp['total'] > 0) {
                $searchInterval['00:00 - 08:00'] = true;
            }
            $filter_data['marketingTimeStart'] = str_replace(' ', 'T', $intervalServer08Reset);
            $filter_data['marketingTimeEnd'] = str_replace(' ', 'T', $intervalServer12);
            $tmp = $ModelCatalogSearch->searchRelevanceProductId($filter_data, $buyerId, SearchParam::SCENE[1], '限时限量活动 即将开始 时间区间列表 ' . $intervalServer08Reset . '_' . $intervalServer12);
            if (is_array($tmp) && array_key_exists('products', $tmp) && array_key_exists('total', $tmp) && $tmp['total'] > 0) {
                $searchInterval['08:00 - 12:00'] = true;
            }
        } else {// $localHour18 <= $nowLocalHour && $nowLocalHour < $localHour24
            $intervalServer08 = Carbon::parse($intervalServer08, $serverTimezone)->addDays(1)->toDateTimeString();
            $intervalServer12 = Carbon::parse($intervalServer12, $serverTimezone)->addDays(1)->toDateTimeString();
            $intervalServer18 = Carbon::parse($intervalServer18, $serverTimezone)->addDays(1)->toDateTimeString();

            $intervalServer24Reset = Carbon::parse($intervalServer24, $serverTimezone)->subSeconds(1)->toDateTimeString();
            $intervalServer08Reset = Carbon::parse($intervalServer08, $serverTimezone)->subSeconds(1)->toDateTimeString();
            $intervalServer12Reset = Carbon::parse($intervalServer12, $serverTimezone)->subSeconds(1)->toDateTimeString();
            $listInterval = [
                '18:00 - 24:00' => $intervalServerNow . '_' . $intervalServer24,
                '00:00 - 08:00' => $intervalServer24 . '_' . $intervalServer08,
                '08:00 - 12:00' => $intervalServer08 . '_' . $intervalServer12,
                '12:00 - 18:00' => $intervalServer12 . '_' . $intervalServer18,
            ];

            $filter_data['marketingTimeStart'] = str_replace(' ', 'T', $intervalServerNow);
            $filter_data['marketingTimeEnd'] = str_replace(' ', 'T', $intervalServer24);
            $tmp = $ModelCatalogSearch->searchRelevanceProductId($filter_data, $buyerId, SearchParam::SCENE[1], '限时限量活动 即将开始 时间区间列表 ' . $intervalServerNow . '_' . $intervalServer24);
            if (is_array($tmp) && array_key_exists('products', $tmp) && array_key_exists('total', $tmp) && $tmp['total'] > 0) {
                $searchInterval['18:00 - 24:00'] = true;
            }
            $filter_data['marketingTimeStart'] = str_replace(' ', 'T', $intervalServer24Reset);
            $filter_data['marketingTimeEnd'] = str_replace(' ', 'T', $intervalServer08);
            $tmp = $ModelCatalogSearch->searchRelevanceProductId($filter_data, $buyerId, SearchParam::SCENE[1], '限时限量活动 即将开始 时间区间列表 ' . $intervalServer24Reset . '_' . $intervalServer08);
            if (is_array($tmp) && array_key_exists('products', $tmp) && array_key_exists('total', $tmp) && $tmp['total'] > 0) {
                $searchInterval['00:00 - 08:00'] = true;
            }
            $filter_data['marketingTimeStart'] = str_replace(' ', 'T', $intervalServer08Reset);
            $filter_data['marketingTimeEnd'] = str_replace(' ', 'T', $intervalServer12);
            $tmp = $ModelCatalogSearch->searchRelevanceProductId($filter_data, $buyerId, SearchParam::SCENE[1], '限时限量活动 即将开始 时间区间列表 ' . $intervalServer08Reset . '_' . $intervalServer12);
            if (is_array($tmp) && array_key_exists('products', $tmp) && array_key_exists('total', $tmp) && $tmp['total'] > 0) {
                $searchInterval['08:00 - 12:00'] = true;
            }
            $filter_data['marketingTimeStart'] = str_replace(' ', 'T', $intervalServer12Reset);
            $filter_data['marketingTimeEnd'] = str_replace(' ', 'T', $intervalServer18);
            $tmp = $ModelCatalogSearch->searchRelevanceProductId($filter_data, $buyerId, SearchParam::SCENE[1], '限时限量活动 即将开始 时间区间列表 ' . $intervalServer12Reset . '_' . $intervalServer18);
            if (is_array($tmp) && array_key_exists('products', $tmp) && array_key_exists('total', $tmp) && $tmp['total'] > 0) {
                $searchInterval['12:00 - 18:00'] = true;
            }
        }

        foreach ($searchInterval as $key => $value) {
            if (!$value) {
                unset($listInterval[$key]);
            }
        }

        return $listInterval;
    }
}
