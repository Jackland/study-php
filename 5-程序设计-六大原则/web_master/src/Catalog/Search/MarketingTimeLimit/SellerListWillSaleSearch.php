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
 * 限时限量活动-店铺页面-即将开始
 * Class SellerListWillSaleSearch
 * @package App\Catalog\Search\MarketingTimeLimit
 */
class SellerListWillSaleSearch
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
        $now = Carbon::now()->toDateTimeString();
        $pointBefore = Carbon::now()->subHours(2)->toDateTimeString();

        return MarketingTimeLimit::query()->alias('m')
            ->leftJoinRelations(['products as mp'])
            ->leftjoin('oc_customer as seller', 'm.seller_id', '=', 'seller.customer_id')
            ->where('m.pre_hot', '=', YesNoEnum::YES)
            ->where('m.effective_time', '>=', $pointBefore)
            ->where('m.effective_time', '<=', $now)
            ->where('m.expiration_time', '>=', $now)
            ->where('m.is_del', '=', YesNoEnum::NO)
            ->where('m.status', '=', 1)
            ->where('mp.qty', '>', 0)
            ->where('mp.status', '=', MarketingTimeLimitProductStatus::NO_RELEASE)
            ->where('seller.country_id', '=', $this->countryId);
    }

    /**
     * 获取某店铺的 下一个开始前24小时预热展示的 限时限量活动的id
     * @param $sellerId
     * @return int
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     * @throws \Exception
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function getNextPreHotId($sellerId)
    {
        $cacheKey = 'MARKETING_TIME_LIMIT_SELLER_LIST_WILL_SALE_SELLER_ID_' . $sellerId . '_BUYER_ID_' . $this->customerId;
        if (cache()->has($cacheKey)) {
            return cache()->get($cacheKey);
        }
        //初始化返回值
        $activeId = 0;
        
        $now = Carbon::now()->toDateTimeString();
        $pointBefore = Carbon::now()->addHours(24)->toDateTimeString();
        $serverTimezone = CountryHelper::getTimezoneByCode('USA');
        $buyerId = $this->customerId;
        $countryId = $this->countryId;
        
        $builder = MarketingTimeLimit::query()->alias('m')
            ->leftJoinRelations('products as mp')
            ->where('m.seller_id', '=', $sellerId)
            ->where('m.pre_hot', '=', YesNoEnum::YES)
            ->where('m.effective_time','>' ,$now)
            ->where('m.effective_time', '<=', $pointBefore)
            ->where('m.status', '=', 1)
            ->where('m.is_del', '=', YesNoEnum::NO)
            ->select(['m.id', 'm.effective_time']);

        if (!$buyerId) {
            //Buyer未登录
            return $builder->value('id');
        } else {
            //Buyer已登录
            $list = $builder->get();
            foreach ($list as $value){
                $activeId = $value->id;
                $start = $value->effective_time->toDateTimeString();
                $startReset = Carbon::parse($start, $serverTimezone)->subSeconds(1)->toDateTimeString();
                $endReset = Carbon::parse($start, $serverTimezone)->addSeconds(1)->toDateTimeString();

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
                /** @var \ModelCatalogSearch $ModelCatalogSearch */
                $ModelCatalogSearch = load()->model('catalog/search');
                $filter_data['marketingTimeStart'] = str_replace(' ', 'T', $startReset);
                $filter_data['marketingTimeEnd'] = str_replace(' ', 'T', $endReset);
                $tmp = $ModelCatalogSearch->searchRelevanceProductId($filter_data, $buyerId, SearchParam::SCENE[1], '限时限量活动 [店铺页面]菜单 即将开始 时间区间列表 ' . $startReset . '_' . $endReset);
                if (is_array($tmp) && array_key_exists('products', $tmp) && array_key_exists('total', $tmp) && $tmp['total'] > 0) {
                    break;
                }
            }
        }
        cache()->set($cacheKey, $activeId, 20);
        return $activeId;
    }
}