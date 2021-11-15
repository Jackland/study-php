<?php

use App\Catalog\Controllers\BaseController;
use App\Catalog\Search\MarketingTimeLimit\HomeListHotSearch;
use App\Catalog\Search\MarketingTimeLimit\HomeListWillEndSearch;
use App\Catalog\Search\MarketingTimeLimit\HomeListWillSaleSearch;
use App\Catalog\Search\MarketingTimeLimit\SellerListOnSaleSearch;
use App\Catalog\Search\MarketingTimeLimit\SellerListWillSaleSearch;
use App\Enums\Common\CountryEnum;
use App\Enums\Common\YesNoEnum;
use App\Enums\Marketing\MarketingTimeLimitStatus;
use App\Helper\CountryHelper;
use App\Models\CustomerPartner\CustomerPartnerToCustomer;
use App\Models\Marketing\MarketingTimeLimit;
use App\Repositories\Marketing\MarketingTimeLimitDiscountRepository;
use App\Repositories\Product\Channel\ChannelRepository;
use App\Repositories\Product\Search\SearchParam;
use App\Services\Marketing\MarketingTimeLimitDiscountService;
use Carbon\Carbon;

/**
 * 限时限量活动
 * Class ControllerAccountMarketingTimeLimit
 * @property ModelCatalogSearch $model_catalog_search
 * @property ModelCustomerpartnerSellerCenterIndex $modelCustomerPartnerSellerCenterIndex
 * @property ModelExtensionModuleProductHome $model_extension_module_product_home
 */
class ControllerAccountMarketingTimeLimit extends BaseController
{
    /**
     * 首页频道-限时限量活动-进行中
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     * @throws Exception
     * @throws Throwable
     */
    public function index()
    {
        $route = request('route');
        $country = session('country', 'USA');
        $countryId = CountryHelper::getCountryByCode($country);
        $isPartner = customer()->isPartner();
        $buyerId = (int)customer()->getId();

        $isOnSale = false;//是否有 进行中 On Sale Now Tab
        $isHot = false;//是否有 实时热抢
        $isWillEnd = false;//是否有 即将结束
        $isWillSale = false;//是否有 即将开始 Tab
        $willEndIntervalHour = '';//即将结束 的剩余时分秒
        $willEndIntervalMinute = '';
        $willEndIntervalSecond = '';

        $now = Carbon::now()->toDateTimeString();
        //进行中
        $result = $this->listAllProcess(0, '', 1, 20, $country, $buyerId);
        $allProductIds = $result['allProductIds'];
        $products = $result['products'];
        $isEnd = $result['isEnd'];
        $isOnSale = (bool)$result['total'];
        //进行中
        if ($isOnSale) {
            //实时热抢
            $resultHot = app(HomeListHotSearch::class, ['customerId' => $buyerId, 'countryId' => $countryId])->searchES();
            if ($resultHot['total']) {
                $isHot = true;
            }
            //即将结束
            $checkWillEnd = app(HomeListWillEndSearch::class, ['customerId' => $buyerId, 'countryId' => $countryId])->checkWillEnd();
            $willEndIntervalHour = $checkWillEnd['willEndIntervalHour'];
            $willEndIntervalMinute = $checkWillEnd['willEndIntervalMinute'];
            $willEndIntervalSecond = $checkWillEnd['willEndIntervalSecond'];
        }
        //进行中的产品第一页
        $dataListAll = [];
        $dataListAll['route'] = $route;
        $dataListAll['products'] = $products;
        $dataListAll['is_partner'] = $isPartner;
        $dataListAll['isLogin'] = (int)customer()->isLogged();
        $dataListAll['login'] = url(['account/login']);
        $arrayListAll = [
            'category_id' => 0,
            'isEnd' => (int)$isEnd,
            'html' => load()->view('account/marketing_time_limit/product_list_all', $dataListAll),
            'page' => 1,
            'page_limit' => 20,
        ];
        
        //即将开始 时间区间列表
        $listWillSaleInterval = app(HomeListWillSaleSearch::class, ['customerId' => $buyerId, 'countryId' => $countryId])->getListWillSaleInterval($countryId, $buyerId);
        //是否有 即将开始
        $isWillSale = count($listWillSaleInterval);

        //分类
        $categories = app(ChannelRepository::class)->getChannelCategoryForMarketingTimeLimitByCountryId($countryId, $allProductIds);

        $data = [];
        $data['isLogin'] = (int)customer()->isLogged();
        $data['route'] = $route;
        $data['now'] = $now;
        $data['isOnSale'] = (int)$isOnSale;
        $data['isHot'] = (int)$isHot;
        $data['isWillEnd'] = (int)$isWillEnd;
        $data['isWillSale'] = (int)$isWillSale;
        $data['sessionWillSaleInterval'] = array_keys($listWillSaleInterval);
        $data['listWillSaleInterval'] = array_values($listWillSaleInterval);
        $data['willEndIntervalHour'] = $willEndIntervalHour;
        $data['willEndIntervalMinute'] = $willEndIntervalMinute;
        $data['willEndIntervalSecond'] = $willEndIntervalSecond;
        $data['category_id'] = 0;
        $data['categories'] = $categories;
        $data['listAllFirst'] = json_encode($arrayListAll);
        return $this->render('account/marketing_time_limit/index', $data, 'home');
    }

    /**
     * 实时热抢 GET
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @throws Exception
     * @throws Throwable
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function listHot()
    {
        //接收参数
        $route = request('route');
        $page = (int)request('page', 1);
        $pageLimit = (int)request('page_limit', 9);

        $isPartner = customer()->isPartner();
        $buyerId = (int)customer()->getId();
        $country = session('country', 'USA');
        $countryId = CountryHelper::getCountryByCode($country);
        $precision = $countryId == CountryEnum::JAPAN ? 0 : 2;

        $search = new HomeListHotSearch($buyerId, $countryId);
        $dataProvider = $search->searchES();
        $list = $dataProvider['products'];// 列表
        $total = $dataProvider['total'];// 总计
        $isEnd = ($page * $pageLimit >= $total) ? 1 : 0;

        $activeIdList = [];
        $activeList = [];
        $productIdArr = [];
        $productList = [];
        $products = [];//用于页面循环

        foreach ($list as $key => $value) {
            $activeId = $value['marketingTimeLimitId'];
            $productId = $value['productId'];
            $productIdArr[$productId] = $productId;
            $products[$productId] = [];//此处占领排序位置
            $activeIdList[] = $activeId;
            $activeList[$productId] = [
                'timeLimitId' => $value['marketingTimeLimitId'],
                'product_id' => $productId,
                'discount' => $value['marketingDiscount'],
                'qty' => $value['marketingQty'],
            ];
        }
        $marketingArr = MarketingTimeLimit::query()->alias('m')
            ->leftJoinRelations('products as mp')
            ->whereIn('m.id', $activeIdList)
            ->select(['m.transaction_type', 'mp.product_id', 'mp.discount'])
            ->get()
            ->keyBy('product_id')
            ->toArray();
        if ($total) {
            /** @var ModelExtensionModuleProductHome $modelExtensionModuleProductHome */
            $modelExtensionModuleProductHome = load()->model('extension/module/product_home');
            $productList = $modelExtensionModuleProductHome->getHomeProductInfo($productIdArr, $buyerId, ['isMarketingTimeLimit' => 1]);
            unset($value);
            foreach ($productList as $key => &$value) {
                $productId = $value['product_id'];
                $discount = $activeList[$productId]['discount'];
                $transactionType = isset($marketingArr[$productId]) ? $marketingArr[$productId]['transaction_type'] : '';
                $priceRangeShow = app(MarketingTimeLimitDiscountRepository::class)->getPriceRangeShow($value['transactionPriceRange'], $transactionType, $discount, session('currency'));
                $value['min_price'] = $priceRangeShow['minPrice'];
                $value['max_price'] = $priceRangeShow['maxPrice'];
                $value['min_price_show'] = $priceRangeShow['minPriceShow'];
                $value['max_price_show'] = $priceRangeShow['maxPriceShow'];
                $value['discountShow'] = round(100 - $discount);
                $value['discount'] = $discount;
                $value['quantity'] = min($value['quantity'], $activeList[$productId]['qty']);
                $value['timeLimitId'] = $activeList[$productId]['timeLimitId'];
                $products[$productId] = $value;//此处填充数据
            }
            unset($value);
        }
        foreach ($products as $productId => $value) {
            if (!is_array($value) || count($value) == 0) {
                unset($products[$productId]);
            }
        }

        //响应参数
        $data = [];
        $data['route'] = $route;
        $data['products'] = $products;
        $data['is_partner'] = $isPartner;
        $data['isLogin'] = (int)customer()->isLogged();
        $data['login'] = url(['account/login']);

        return $this->json([
            'isEnd' => (int)$isEnd,
            'html' => load()->view('account/marketing_time_limit/products', $data),
            'page' => $page,
            'page_limit' => $pageLimit,
        ]);
    }

    /**
     * 即将结束 GET
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @throws Exception
     * @throws Throwable
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function listWillEnd()
    {
        $isPartner = customer()->isPartner();
        $buyerId = (int)customer()->getId();
        $country = session('country', 'USA');
        $countryId = CountryHelper::getCountryByCode($country);
        $precision = $countryId == CountryEnum::JAPAN ? 0 : 2;
        //接收参数
        $route = request('route');
        $page = (int)request('page', 1);
        $pageLimit = (int)request('page_limit', 9);

        $search = new HomeListWillEndSearch($buyerId, $countryId);
        $dataProvider = $search->searchES();
        $list = $dataProvider['products'];
        $total = $dataProvider['total']; // 总计
        $isEnd = ($page * $pageLimit >= $total) ? 1 : 0;

        $productIdArr = [];
        $activeIdList = [];
        $activeList = [];//key = product_id
        $productList = [];
        $products = [];//用于页面循环

        foreach ($list as $key => $value) {
            $activeId = $value['marketingTimeLimitId'];
            $productId = $value['productId'];
            $productIdArr[$productId] = $productId;
            $products[$productId] = [];//此处占领排序位置
            $activeIdList[] = $activeId;
            $activeList[$productId] = [
                'timeLimitId' => $value['marketingTimeLimitId'],
                'product_id' => $productId,
                'discount' => $value['marketingDiscount'],
                'qty' => $value['marketingQty'],
            ];
        }
        $marketingArr = MarketingTimeLimit::query()->alias('m')
            ->leftJoinRelations('products as mp')
            ->whereIn('m.id', $activeIdList)
            ->select(['m.transaction_type', 'mp.product_id', 'mp.discount'])
            ->get()
            ->keyBy('product_id')
            ->toArray();
        if ($total) {
            /** @var ModelExtensionModuleProductHome $modelExtensionModuleProductHome */
            $modelExtensionModuleProductHome = load()->model('extension/module/product_home');
            $productList = $modelExtensionModuleProductHome->getHomeProductInfo($productIdArr, $buyerId, ['isMarketingTimeLimit' => 1]);
            unset($value);
            foreach ($productList as $key => &$value) {
                $productId = $value['product_id'];
                $discount = $activeList[$productId]['discount'];
                $transactionType = isset($marketingArr[$productId]) ? $marketingArr[$productId]['transaction_type'] : '';
                $priceRangeShow = app(MarketingTimeLimitDiscountRepository::class)->getPriceRangeShow($value['transactionPriceRange'], $transactionType, $discount, session('currency'));
                $value['min_price'] = $priceRangeShow['minPrice'];
                $value['max_price'] = $priceRangeShow['maxPrice'];
                $value['min_price_show'] = $priceRangeShow['minPriceShow'];
                $value['max_price_show'] = $priceRangeShow['maxPriceShow'];
                $value['discountShow'] = round(100 - $discount);
                $value['discount'] = $discount;
                $value['quantity'] = min($value['quantity'], $activeList[$productId]['qty']);
                $value['timeLimitId'] = $activeList[$productId]['timeLimitId'];
                $products[$productId] = $value;//此处填充数据
            }
            unset($value);
        }
        foreach ($products as $productId => $value) {
            if (!is_array($value) || count($value) == 0) {
                unset($products[$productId]);
            }
        }

        $data = [];
        $data['route'] = $route;
        $data['products'] = $products;
        $data['is_partner'] = $isPartner;
        $data['isLogin'] = (int)customer()->isLogged();
        $data['login'] = url(['account/login']);

        mark:
        return $this->json([
            'isEnd' => (int)$isEnd,
            'html' => load()->view('account/marketing_time_limit/products', $data),
            'page' => $page,
            'page_limit' => $pageLimit,
        ]);
    }

    /**
     * All active products GET
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @throws Exception
     * @throws Throwable
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function listAll()
    {
        //响应参数
        $isEnd = 1;
        $data = [];
        $products = [];//用于页面内循环

        $isPartner = customer()->isPartner();
        $buyerId = (int)customer()->getId();
        $country = session('country', 'USA');
        $countryId = CountryHelper::getCountryByCode($country);
        $precision = $countryId == CountryEnum::JAPAN ? 0 : 2;

        //接收参数
        $route = request('route');
        $categoryId = (int)request('category_id', 0);
        $sort = request('sort', 'discount');
        $page = (int)request('page', 1);
        $pageLimit = (int)request('page_limit', 20);

        $result = $this->listAllProcess($categoryId, $sort, $page, $pageLimit, $country, $buyerId);
        $products = $result['products'];
        $isEnd = $result['isEnd'];


        $data['route'] = $route;
        $data['products'] = $products;
        $data['is_partner'] = $isPartner;
        $data['isLogin'] = (int)customer()->isLogged();
        $data['login'] = url(['account/login']);
        end:
        return $this->json([
            'category_id' => $categoryId,
            'isEnd' => (int)$isEnd,
            'html' => load()->view('account/marketing_time_limit/product_list_all', $data),
            'page' => $page,
            'page_limit' => $pageLimit,
        ]);
    }

    /**
     * @param int $categoryId
     * @param string $sort
     * @param int $page
     * @param int $pageLimit
     * @param string $country
     * @param int $buyerId
     * @return array
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     * @throws Exception
     */
    protected function listAllProcess($categoryId, $sort, $page, $pageLimit, $country, $buyerId)
    {
        //返回值初始化
        $allProductIds = [];
        $products = [];
        $total = 0;
        $isEnd = 0;

        //quantity|price|discount
        //marketingQty|marketingPrice|marketingDiscount
        $sortMap = [
            'quantity' => 'marketingQty',
            'price' => 'marketingPrice',
            'discount' => 'marketingDiscount',
        ];

        $filter_data = [];
        $filter_data['search'] = null;
        $filter_data['category_id'] = $categoryId;
        $filter_data['sort'] = $sortMap[$sort] ?? 'marketingDiscount';
        $filter_data['order'] = $order = request('order', 'ASC');
        $filter_data['page'] = $page;
        $filter_data['limit'] = $pageLimit;
        $filter_data['country'] = $country;
        $filter_data['min_quantity'] = 1;
        //限时限量活动
        $filter_data['discountPriceBoundMin'] = request('min_price', '');
        $filter_data['discountPriceBoundMax'] = request('max_price', '');
        $filter_data['marketingProductStatus'] = 1;
        $filter_data['marketingQtyMin'] = request('min_quantity', '1');
        $filter_data['marketingQtyMin'] == '' && $filter_data['marketingQtyMin'] = 1;
        $filter_data['marketingQtyMax'] = request('max_quantity', '');
        $filter_data['marketingTime'] = Carbon::now()->format('Y-m-d\TH:i:s');//Java那边要求这个参数中间要有一个字母T

        $this->load->model('catalog/search');
        $tmp = $this->model_catalog_search->searchRelevanceProductId($filter_data, $buyerId, SearchParam::SCENE[1], '限时限量活动 All active products');
        if (!is_array($tmp) || !array_key_exists('products', $tmp) || !array_key_exists('total', $tmp) || $tmp['total'] < 1) {
            goto end;
        }
        $allProductIds = $tmp['allProductIds'];//array
        $list = $tmp['products'];
        $total = $tmp['total'];
        $isEnd = ($page * $pageLimit >= $total) ? 1 : 0;

        $productIdArr = [];
        $activeIdList = [];//key=activeId, value=activeId
        $activeList = [];//key = product_id
        $productList = [];

        foreach ($list as $key => $value) {
            $activeId = $value['marketingTimeLimitId'];
            $productId = $value['productId'];
            $products[$productId] = [];//此处占领排序位置
            $productIdArr[$productId] = $productId;
            $activeIdList[$activeId] = $activeId;
            $activeList[$productId] = [
                'timeLimitId' => $value['marketingTimeLimitId'],
                'product_id' => $productId,
                'discount' => $value['marketingDiscount'],
                'price' => bcdiv($value['productDataHolder']['currentPrice'], 100),
                'qty' => $value['marketingQty'],
            ];
        }
        $marketingArr = MarketingTimeLimit::query()->alias('m')
            ->leftJoinRelations('products as mp')
            ->whereIn('m.id', $activeIdList)
            ->select(['m.transaction_type', 'mp.product_id', 'mp.discount'])
            ->get()
            ->keyBy('product_id')
            ->toArray();
        if ($total) {
            /** @var ModelExtensionModuleProductHome $modelExtensionModuleProductHome */
            $modelExtensionModuleProductHome = load()->model('extension/module/product_home');
            $productList = $modelExtensionModuleProductHome->getHomeProductInfo($productIdArr, $buyerId, ['isMarketingTimeLimit' => true]);
            unset($value);
            foreach ($productList as $key => &$value) {
                $productId = $value['product_id'];
                $discount = $activeList[$productId]['discount'];
                $transactionType = isset($marketingArr[$productId]) ? $marketingArr[$productId]['transaction_type'] : '';
                $priceRangeShow = app(MarketingTimeLimitDiscountRepository::class)->getPriceRangeShow($value['transactionPriceRange'], $transactionType, $discount, session('currency'));
                $value['min_price'] = $priceRangeShow['minPrice'];
                $value['max_price'] = $priceRangeShow['maxPrice'];
                $value['min_price_show'] = $priceRangeShow['minPriceShow'];
                $value['max_price_show'] = $priceRangeShow['maxPriceShow'];
                $value['discountShow'] = round(100 - $discount);
                $value['discount'] = $discount;
                $value['quantity'] = min($value['quantity'], $activeList[$productId]['qty']);
                $value['timeLimitId'] = $activeList[$productId]['timeLimitId'];
                $products[$productId] = $value;//此处填充数据
            }
            unset($value);
        }
        foreach ($products as $productId => $value) {
            if (!is_array($value) || count($value) == 0) {
                unset($products[$productId]);
            }
        }

        end:
        $data = [
            'allProductIds' => $allProductIds,
            'products' => $products,
            'total' => $total,
            'isEnd' => $isEnd,
        ];
        return $data;
    }

    /**
     * 即将开始 GET
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @throws Exception
     * @throws Throwable
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function listWillSale()
    {
        //接收参数
        $route = request('route');
        $page = (int)request('page', 1);
        $pageLimit = (int)request('page_limit', 16);
        $intervalSession = request('intervalSession', '');
        $interval = request('interval', '');

        //响应参数
        $isEnd = 1;
        $data = [];

        $isPartner = customer()->isPartner();
        $buyerId = (int)customer()->getId();
        $country = session('country', 'USA');
        $countryId = CountryHelper::getCountryByCode($country);
        $precision = $countryId == CountryEnum::JAPAN ? 0 : 2;
        $now = Carbon::now()->toDateTimeString();


        $search = new HomeListWillSaleSearch($buyerId, $countryId);
        $dataProvider = $search->search($this->request->query->all());
        $list = $dataProvider->getList();
        $total = $dataProvider->getTotalCount(); // 总计
        $isEnd = ($page * $pageLimit >= $total) ? 1 : 0;

        /** @var ModelExtensionModuleProductHome $modelExtensionModuleProductHome */
        $modelExtensionModuleProductHome = load()->model('extension/module/product_home');
        /** @var ModelCustomerpartnerSellerCenterIndex $modelCustomerPartnerSellerCenterIndex */
        //$modelCustomerPartnerSellerCenterIndex = load()->model('customerpartner/seller_center/index');

        $active2Infos = [];
        $sellerId2SellerId = [];
        $activeIdArr = [];
        $activeId2sellerIdArr = [];
        $productIdArr = [];
        $sellerAvatarArr = [];//Seller 头像
        $marketingArr = [];

        $serverTimezone = CountryHelper::getTimezoneByCode('USA');
        $target = new \DateTime($now);
        foreach ($list as $key => $value) {
            $activeId = $value->id;
            $sellerId = $value->seller_id;
            $sellerId2SellerId[$sellerId] = $sellerId;
            $activeIdArr[$activeId] = $activeId;
            $activeId2sellerIdArr[$activeId] = $sellerId;

            $effectiveTimeString = Carbon::parse(Carbon::parse($value->effective_time, $serverTimezone))->toDateTimeString();
            $origin = new \DateTime($effectiveTimeString);
            $interval = $origin->diff($target);
            $startAfter = $interval->format('%H:%I:%S');
            $active2Infos[$activeId][$sellerId]['timeLimitId'] = $activeId;
            $active2Infos[$activeId][$sellerId]['seller_id'] = $sellerId;
            $active2Infos[$activeId][$sellerId]['effective_time'] = $effectiveTimeString;
            $active2Infos[$activeId][$sellerId]['effective_time_before'] = Carbon::parse($value->effective_time, $serverTimezone)->subSeconds(1)->toDateTimeString();
            $active2Infos[$activeId][$sellerId]['effective_time_after'] = Carbon::parse($value->effective_time, $serverTimezone)->addSeconds(1)->toDateTimeString();
            $active2Infos[$activeId][$sellerId]['start_after'] = $startAfter;
            $active2Infos[$activeId][$sellerId]['max_discount'] = $value->max_discount;
            $active2Infos[$activeId][$sellerId]['max_discount_show'] = $value->max_discount_show;
            $active2Infos[$activeId][$sellerId]['screenname'] = $value->screenname;
            $active2Infos[$activeId][$sellerId]['avatar_show'] = '';
            $active2Infos[$activeId][$sellerId]['tokenTime'] = app(MarketingTimeLimitDiscountService::class)->generateToken($activeId);
            $active2Infos[$activeId][$sellerId]['productsSearch'] = [];

            $this->request->query->set('sellerId', $sellerId);
            $this->request->query->set('effective_time_before', $active2Infos[$activeId][$sellerId]['effective_time_before']);
            $this->request->query->set('effective_time_after', $active2Infos[$activeId][$sellerId]['effective_time_after']);
            $search = new HomeListWillSaleSearch($buyerId, $countryId);
            $dataProvider = $search->searchESForSellerId($this->request->query->all());
            $list = $dataProvider['products'];
            $total = $dataProvider['total']; // 总计

            foreach ($list as $k => $v) {
                if (count($active2Infos[$activeId][$sellerId]['productsSearch']) >= 3) {
                    break;
                }
                $productId = $v['productId'];
                $productIdArr[$productId] = $productId;
                $active2Infos[$activeId][$sellerId]['productsSearch'][$productId] = $v;
                $active2Infos[$activeId][$sellerId]['productList'][$productId] = $productId;//排序占位
            }
            if (count($active2Infos[$activeId][$sellerId]['productsSearch']) < 1) {
                unset($active2Infos[$activeId]);
            }
        }

        $marketingArray = MarketingTimeLimit::query()->alias('m')
            ->leftJoinRelations('products as mp')
            ->whereIn('m.id', $activeIdArr)
            ->select(['m.id', 'm.transaction_type', 'mp.product_id', 'mp.discount'])
            ->get()
            ->toArray();
        foreach ($marketingArray as $key => $value) {
            $activeId = $value['id'];
            $productId = $value['product_id'];
            $marketingArr[$activeId][$productId] = $value;
        }

        //Seller 店铺名称/头像
        foreach ($sellerId2SellerId as $sellerId) {
            if (isset($sellerAvatarArr[$sellerId])) {
                continue;
            }
            $seller = CustomerPartnerToCustomer::find($sellerId);
            $sellerAvatarArr[$sellerId]['avatar_show'] = $seller->avatar_show;
            $sellerAvatarArr[$sellerId]['screenname'] = $seller->screenname;
        }

        $productList = $modelExtensionModuleProductHome->getHomeProductInfo($productIdArr, $buyerId, ['isMarketingTimeLimit' => 1]);
        $productList = array_column($productList, null, 'product_id');

        foreach ($active2Infos as $activeId => $activeInfo) {
            foreach ($activeInfo as $sellerId => $sellerInfo) {
                $active2Infos[$activeId][$sellerId]['avatar_show'] = $sellerAvatarArr[$sellerId]['avatar_show'];
                foreach ($sellerInfo['productsSearch'] as $productId => $search) {
                    if (isset($productList[$productId])) {
                        $discount = $search['marketingDiscount'];
                        $transactionType = $marketingArr[$activeId][$productId]['transaction_type'];
                        $priceRangeShow = app(MarketingTimeLimitDiscountRepository::class)->getPriceRangeShow($productList[$productId]['transactionPriceRange'], $transactionType, $discount, session('currency'));
                        $productList[$productId]['min_price'] = $priceRangeShow['minPrice'];
                        $productList[$productId]['max_price'] = $priceRangeShow['maxPrice'];
                        $productList[$productId]['min_price_show'] = $priceRangeShow['minPriceShow'];
                        $productList[$productId]['max_price_show'] = $priceRangeShow['maxPriceShow'];
                        $productList[$productId]['discountShow'] = 100 - $discount;
                        $active2Infos[$activeId][$sellerId]['productList'][$productId] = $productList[$productId];
                    }
                    if (!is_array($active2Infos[$activeId][$sellerId]['productList'][$productId])) {
                        unset($active2Infos[$activeId][$sellerId]['productList'][$productId]);
                    }
                }
            }
        }
        $data = [];
        $data['route'] = $route;
        $data['sellerAvatarArr'] = $sellerAvatarArr;
        $data['resultList'] = $active2Infos;
        $data['is_partner'] = $isPartner;
        $data['isLogin'] = (int)customer()->isLogged();
        $data['login'] = url(['account/login']);

        return $this->json([
            'session' => $intervalSession,
            'isEnd' => (int)$isEnd,
            'html' => load()->view('account/marketing_time_limit/product_list_will_sale', $data),
            'page' => $page,
            'page_limit' => $pageLimit,
        ]);
    }

    /**
     * 店铺-限时限量活动--进行中
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     * @throws Exception
     */
    public function sellerOnSale()
    {
        //接收参数
        $route = request('route');
        $sellerId = (int)request('id');
        $page = (int)request('page', 1);
        $pageLimit = (int)request('page_limit', 9);
        if (!$sellerId) {
            return $this->redirect(['common/home']);
        }

        $isPartner = customer()->isPartner();
        $buyerId = (int)customer()->getId();
        $country = session('country', 'USA');
        $countryId = CountryHelper::getCountryByCode($country);
        $precision = $countryId == CountryEnum::JAPAN ? 0 : 2;
        $now = Carbon::now()->toDateTimeString();

        $redirectPreHotUrl = '';
        $nextPreHotId = app(SellerListWillSaleSearch::class, ['customerId' => $buyerId, 'countryId' => $countryId])->getNextPreHotId($sellerId);
        if ($nextPreHotId) {
            $redirectPreHotUrl = url(['account/marketing_time_limit/sellerWillSale', 'id' => $sellerId, 'timeLimitId' => $nextPreHotId, 'time_token' => app(MarketingTimeLimitDiscountService::class)->generateToken($nextPreHotId)]);
        }

        $search = new SellerListOnSaleSearch($buyerId, $countryId);
        $dataProvider = $search->searchES($this->request->query->all());
        $list = $dataProvider['products'];
        $total = $dataProvider['total']; // 总计

        if (!$total) {
            return response()->redirectTo(url(['seller_store/home', 'id' => $sellerId]));
        }

        $arrInfo = reset($list);
        $activeId = $arrInfo['marketingTimeLimitId'];
        $expirationTimeString = $arrInfo['marketingExpirationTime'];
        $expirationTimestamp = strtotime($expirationTimeString);
        $origin = new \DateTime($expirationTimeString);
        $target = new \DateTime($now);
        $interval = $origin->diff($target);
        $willEndIntervalDay = $interval->format('%D');
        $willEndIntervalHour = $interval->format('%H');
        $willEndIntervalMinute = $interval->format('%I');
        $willEndIntervalSecond = $interval->format('%S');

        $info = MarketingTimeLimit::query()->alias('m')->where('id', '=', $activeId)->first();
        if ($info->status == MarketingTimeLimitStatus::STOPED) {//过期 正常情况下，不会执行这段代码
            $this->load->language('marketing_campaign/activity');
            $data['heading_title'] = '';
            $data['continue'] = url(['seller_store/home', 'id' => $sellerId]);
            $data['text_error'] = $this->language->get('text_error');
            return $this->render('error/not_found', $data, 'buyer_seller_store');
        }
        $rules = app(MarketingTimeLimitDiscountRepository::class)->getTimeLimitDiscountShowMsg($info, 2, 0);
        $marketingArr = MarketingTimeLimit::query()->alias('m')
            ->leftJoinRelations('products as mp')
            ->where('m.id', '=', $activeId)
            ->select(['m.transaction_type', 'mp.product_id', 'mp.discount'])
            ->get()
            ->keyBy('product_id')
            ->toArray();

        $productId2DiscountArr = array_column($list, null, 'productId');

        $products = [];// ['productId'=>[]，...]
        //根据产品ID计算产品基本信息
        $products = $productIdArr = array_column($list, 'productId', 'productId');

        /** @var ModelExtensionModuleProductHome $modelExtensionModuleProductHome */
        $modelExtensionModuleProductHome = load()->model('extension/module/product_home');
        $productList = $modelExtensionModuleProductHome->getHomeProductInfo($productIdArr, $buyerId, ['isMarketingTimeLimit' => 1]);
        unset($value);
        foreach ($productList as $key => &$value) {
            $productId = $value['product_id'];
            $timeLimitId = $activeId;
            $discount = $productId2DiscountArr[$productId]['marketingDiscount'];
            $transactionType = isset($marketingArr[$productId]) ? $marketingArr[$productId]['transaction_type'] : '';
            $old_min_price = $value['min_price'];
            $old_max_price = $value['max_price'];
            $old_min_price_show = $value['min_price_show'];
            $old_max_price_show = $value['max_price_show'];
            $value['delete_price_show'] = '';
            if ($old_min_price == $old_max_price) {
                $value['delete_price_show'] = $old_min_price_show;
            } else {
                $value['delete_price_show'] = $old_min_price_show . '-' . $old_max_price_show;
            }
            $priceRangeShow = app(MarketingTimeLimitDiscountRepository::class)->getPriceRangeShow($value['transactionPriceRange'], $transactionType, $discount, session('currency'));
            $value['min_price'] = $priceRangeShow['minPrice'];
            $value['max_price'] = $priceRangeShow['maxPrice'];
            $value['min_price_show'] = $priceRangeShow['minPriceShow'];
            $value['max_price_show'] = $priceRangeShow['maxPriceShow'];
            $value['discountShow'] = (100 - $discount);
            $value['quantity'] = min($value['quantity'], $productId2DiscountArr[$productId]['marketingQty']);
            $value['timeLimitId'] = $timeLimitId;
            $products[$productId] = $value;//此处填充数据
        }
        unset($value);
        foreach ($products as $productId => $value) {
            if (!is_array($value) || count($value) == 0) {
                unset($products[$productId]);
            }
        }

        //返回参数
        $data = [
            'redirectPreHotUrl' => $redirectPreHotUrl,
            'route' => $route,
            'sellerId' => $sellerId,
            'maxDiscountShow' => $info->max_discount,
            'expirationTimestamp' => $expirationTimestamp,
            'willEndIntervalDay' => $willEndIntervalDay,
            'willEndIntervalHour' => $willEndIntervalHour,
            'willEndIntervalMinute' => $willEndIntervalMinute,
            'willEndIntervalSecond' => $willEndIntervalSecond,
            'productList' => $products,
            'rules' => $rules,
        ];
        $data['is_partner'] = $isPartner;
        $data['isLogin'] = (int)customer()->isLogged();
        $data['login'] = url(['account/login']);
        return $this->render('account/marketing_time_limit/seller_on_sale', $data, 'buyer_seller_store');
    }

    /**
     * 店铺-限时限量活动--即将开始
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     * @throws Exception
     */
    public function sellerWillSale()
    {
        //接收参数
        $route = request('route');
        $sellerId = (int)request('id');
        $activeId = $timeLimitId = request()->query->getInt('timeLimitId');
        $token = request()->query->get('time_token');
        $checkToken = app(MarketingTimeLimitDiscountService::class)->generateToken($timeLimitId);
        if (!$sellerId || $token != $checkToken) {
            $data['continue'] = url(['seller_store/home', 'id' => $sellerId]);
            $data['text_error'] = '<h1>The page you requested cannot be found!</h1>';
            return $this->render('error/not_found', $data, 'buyer_seller_store');
        }

        $isPartner = customer()->isPartner();
        $buyerId = (int)customer()->getId();
        $country = session('country', 'USA');
        $countryId = CountryHelper::getCountryByCode($country);
        $precision = $countryId == CountryEnum::JAPAN ? 0 : 2;
        $now = Carbon::now()->toDateTimeString();
        $serverTimezone = CountryHelper::getTimezoneByCode('USA');

        $info = MarketingTimeLimit::query()->alias('m')
            ->where('m.id', '=', $timeLimitId)
            ->where('m.is_del', '=', YesNoEnum::NO)
            ->first();
        if (!$info || $info->pre_hot == YesNoEnum::NO) {
            $data['continue'] = url(['seller_store/home', 'id' => $sellerId]);
            $data['text_error'] = '<h1>The page you requested cannot be found!</h1>';
            return $this->render('error/not_found', $data, 'buyer_seller_store');
        }
        if ($info->status == MarketingTimeLimitStatus::STOPED) {//过期
            $this->load->language('marketing_campaign/activity');
            $data['heading_title'] = '';
            $data['continue'] = url(['seller_store/home', 'id' => $sellerId]);
            $data['text_error'] = $this->language->get('text_error');
            return $this->render('error/not_found', $data, 'buyer_seller_store');
        }
        $effectiveTime = $info->effective_time;
        $effectiveTimeString = $info->effective_time->toDateTimeString();
        $expirationTime = $info->expiration_time;
        $expirationTimeString = $info->expiration_time->toDateTimeString();
        $startRest = Carbon::parse($effectiveTimeString, $serverTimezone)->subSeconds(1)->toDateTimeString();
        $endRest = Carbon::parse($expirationTimeString, $serverTimezone)->addSeconds(1)->toDateTimeString();
        if ($effectiveTime <= $now && $now <= $expirationTime) {
            //进行中
            return response()->redirectTo(url(['account/marketing_time_limit/sellerOnSale', 'id' => $sellerId, 'timeLimitId' => $timeLimitId, 'time_token' => $token]));
        }

        $filter_data = [];
        $filter_data['search'] = null;
        $filter_data['category_id'] = 0;
        $filter_data['sort'] = 'marketingDiscount';
        $filter_data['order'] = 'ASC';
        $filter_data['page'] = 1;
        $filter_data['limit'] = 100;
        $filter_data['country'] = CountryHelper::getCountryCodeById($countryId);
        //限时限量活动
        $filter_data['discountPriceBoundMin'] = '';
        $filter_data['discountPriceBoundMax'] = '';
        $filter_data['marketingProductStatus'] = 1;
        $filter_data['marketingQtyMin'] = 1;
        $filter_data['marketingQtyMax'] = '';
        $filter_data['marketingTimeStart'] = str_replace(' ', 'T', $startRest);//Java那边要求这个参数中间要有一个字母T
        $filter_data['marketingTimeEnd'] = str_replace(' ', 'T', $endRest);
        $filter_data['seller_id'] = $sellerId;
        /** @var \ModelCatalogSearch $ModelCatalogSearch */
        $ModelCatalogSearch = load()->model('catalog/search');
        $tmp = $ModelCatalogSearch->searchRelevanceProductId($filter_data, $buyerId, SearchParam::SCENE[1], '限时限量活动 即将开始 店铺即将开始 ' . $startRest . '_' . $endRest . ' sellerId=' . $sellerId);
        if (is_array($tmp) && array_key_exists('products', $tmp) && array_key_exists('total', $tmp) && $tmp['total'] > 0) {
            $productsSearch = $tmp['products'];
            $productId2Infos = [];      //[productId]=>[productId=>'',......],......]
            $products = [];             //[productId=>productId]
            foreach ($productsSearch as $value) {
                $activeId = $value['marketingTimeLimitId'];
                $discount = $value['marketingDiscount'];
                $productId = $value['productId'];
                $productIdArr[] = $productId;
                $productId2Infos[$productId] = [
                    'marketingEffectiveTime' => $value['marketingEffectiveTime'],
                    'discount' => $discount,
                    'productId' => $productId,
                    'qty' => $value['marketingQty'],
                ];
                $products[$productId] = $productId;//此处占位排序
            }

            $marketingArr = MarketingTimeLimit::query()->alias('m')
                ->leftJoinRelations('products as mp')
                ->where('m.id', '=', $activeId)
                ->select(['m.transaction_type', 'mp.product_id', 'mp.discount'])
                ->get()
                ->keyBy('product_id')
                ->toArray();
            $origin = new \DateTime($effectiveTimeString);
            $target = new \DateTime($now);
            $interval = $origin->diff($target);
            $willSaleIntervalDay = $interval->format('%D');
            $willSaleIntervalHour = $interval->format('%H');
            $willSaleIntervalMinute = $interval->format('%I');
            $willSaleIntervalSecond = $interval->format('%S');

            $rules = app(MarketingTimeLimitDiscountRepository::class)->getTimeLimitDiscountShowMsg($info, 2, 0);

            /** @var ModelExtensionModuleProductHome $modelExtensionModuleProductHome */
            $modelExtensionModuleProductHome = load()->model('extension/module/product_home');
            $productList = $modelExtensionModuleProductHome->getHomeProductInfo($productIdArr, $buyerId, ['isMarketingTimeLimit' => 1]);
            unset($value);
            foreach ($productList as $key => &$value) {
                $productId = $value['product_id'];
                $timeLimitId = $activeId;
                $discount = $productId2Infos[$productId]['discount'];
                $transactionType = isset($marketingArr[$productId]) ? $marketingArr[$productId]['transaction_type'] : '';
                $old_min_price = $value['min_price'];
                $old_max_price = $value['max_price'];
                $old_min_price_show = $value['min_price_show'];
                $old_max_price_show = $value['max_price_show'];
                $value['delete_price_show'] = '';
                if ($old_min_price == $old_max_price) {
                    $value['delete_price_show'] = $old_min_price_show;
                } else {
                    $value['delete_price_show'] = $old_min_price_show . '-' . $old_max_price_show;
                }
                $priceRangeShow = app(MarketingTimeLimitDiscountRepository::class)->getPriceRangeShow($value['transactionPriceRange'], $transactionType, $discount, session('currency'));
                $value['min_price'] = $priceRangeShow['minPrice'];
                $value['max_price'] = $priceRangeShow['maxPrice'];
                $value['min_price_show'] = $priceRangeShow['minPriceShow'];
                $value['max_price_show'] = $priceRangeShow['maxPriceShow'];
                $value['discountShow'] = 100 - $discount;
                $value['discount'] = $discount;
                $value['quantity'] = min($value['quantity'], $productId2Infos[$productId]['qty']);
                $value['timeLimitId'] = $timeLimitId;
                $products[$productId] = $value;//此处填充数据
            }
            unset($value);
            foreach ($products as $productId => $value) {
                if (!is_array($value) || count($value) == 0) {
                    unset($products[$productId]);
                }
            }

            //返回参数
            $data = [
                'route' => $route,
                'sellerId' => $sellerId,
                'maxDiscountShow' => $info->max_discount,
                'willSaleIntervalDay' => $willSaleIntervalDay,
                'willSaleIntervalHour' => $willSaleIntervalHour,
                'willSaleIntervalMinute' => $willSaleIntervalMinute,
                'willSaleIntervalSecond' => $willSaleIntervalSecond,
                'productList' => $products,
                'rules' => $rules,
            ];
            $data['is_partner'] = $isPartner;
            $data['isLogin'] = (int)customer()->isLogged();
            $data['login'] = url(['account/login']);
            return $this->render('account/marketing_time_limit/seller_will_sale', $data, 'buyer_seller_store');
        } else {
            return response()->redirectTo(url(['seller_store/home', 'id' => $sellerId]));
        }
    }
}
