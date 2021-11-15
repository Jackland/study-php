<?php

namespace App\Repositories\Common;

use App\Components\RemoteApi;
use App\Components\Storage\StorageCloud;
use App\Components\Traits\RequestCachedDataTrait;
use App\Enums\Common\HomeChannelEnum;
use App\Enums\Product\Channel\CacheTimeToLive;
use App\Helper\CountryHelper;
use App\Models\HomePage\HomePageOperation;
use App\Repositories\Product\Channel\Module\FeaturedStores;
use App\Repositories\Product\Channel\Module\NewStores;
use Exception;

class HomeRepository
{
    const CHANNEL_LIMIT = 2;
    const CHANNEL_MAX = 6;
    const STORE_LIMIT = 4;

    use RequestCachedDataTrait;

    /**
     * 首页内容分布在不同的文件之中获取不同的数据
     * @param array $setting
     * @param int $cacheRefresh
     * @return array
     */

    public function getSlideShowData(array $setting, int $cacheRefresh = 0): array
    {
        $data = [];
        static $module = 0;
        /** @var \ModelDesignBanner $model_design_banner */
        $model_design_banner = load()->model('design/banner');
        /** @var \ModelExtensionModuleProductCategory $model_extension_module_product_category */
        $model_extension_module_product_category = load()->model('extension/module/product_category');
        $data['banners'] = $this->getBannerData($setting, $cacheRefresh);
        $data['logged'] = customer()->isLogged();
        $data['categories'] = $model_extension_module_product_category->getDefaultCategoryList();
        $data['module'] = $module++;
        ['data' => $sellerData, 'type' => $storeType, 'is_end' => $isEnd] = $this->getBannersRightSellers();
        $data['isNewStores'] = $storeType == HomeChannelEnum::FEATURE_STORE ? 0 : 1;
        [$data['banners_right'], $data['banners_right_is_end']] = $model_design_banner->getStoresInfo($sellerData, $isEnd);
        return $data;
    }

    private function getBannersRightSellers(): array
    {
        return $this->requestCachedData([__CLASS__, __FUNCTION__, CountryHelper::getCountryByCode(session()->get('country'))], function () {
            $newStore = app(NewStores::class);
            $newStore->setShowNum(self::STORE_LIMIT);
            [$ret, $isEnd] = $newStore->getNewSellerIds();
            if (count($ret) > 2) {
                return [
                    'data' => $ret,
                    'type' => HomeChannelEnum::NEW_STORE,
                    'is_end' => $isEnd,
                ];
            }
            $featureStore = app(FeaturedStores::class);
            $featureStore->setShowNum((self::STORE_LIMIT));
            [$ret, $isEnd] = $featureStore->getSellerIds(1, self::STORE_LIMIT);
            return [
                'data' => $ret,
                'type' => HomeChannelEnum::FEATURE_STORE,
                'is_end' => $isEnd,
            ];
        });
    }

    public static function getBannerCacheKey(): array
    {
        return [
            'USA' => ['getBannerData', 'USA'],
            'GBR' => ['getBannerData', 'GBR'],
            'DEU' => ['getBannerData', 'DEU'],
            'JPN' => ['getBannerData', 'JPN'],
        ];
    }

    public function getBannerData(array $setting, int $cacheRefresh = 0): array
    {
        $cacheKey = self::getBannerCacheKey()[session('country')];
        if (cache()->has($cacheKey) && $cacheRefresh != 2) {
            return cache()->get($cacheKey);
        }
        /** @var \ModelToolImage $model_tool_image */
        $model_tool_image = load()->model('tool/image');
        /** @var \ModelDesignBanner $model_design_banner */
        $model_design_banner = load()->model('design/banner');
        // 首页轮播图的
        $banners = [];
        $country = session('country');
        $results = $model_design_banner->getBanner($setting['banner_id'], $country);
        foreach ($results as $result) {
            if ($result['image']) {
                // 获取 img的大小
                $image = StorageCloud::image()->getUrl($result['image'], ['w' => $setting['width'], 'h' => $setting['height'], 'check-exist' => false]);
                $banners[] = [
                    'title' => $result['title'],
                    'link' => $result['link'],
                    'image' => $image,
                ];
            } elseif (is_file(DIR_IMAGE . 'no_image.png')) {
                $banners[] = [
                    'title' => $result['title'],
                    'link' => $result['link'],
                    'image' => $model_tool_image->resize('no_image.png', $setting['width'], $setting['height'])
                ];
            }
        }

        cache()->set($cacheKey, $banners, CacheTimeToLive::THREE_HOURS);
        return $banners;
    }

    /**
     * 首页频道页数据处理,频道页数据暂定5分钟缓存
     * @param array $setting
     * @param int $cacheRefresh 0
     * @return array
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function getFeatureData(array $setting, int $cacheRefresh = 0): array
    {
        load()->language('extension/module/featured');
        /** @var \ModelAccountTicket $model_account_ticket */
        $model_account_ticket = load()->model('account/ticket');
        load()->model('catalog/product');
        load()->model('catalog/product_column');
        load()->model('catalog/ProductPrice');
        load()->model('tool/image');
        $setting['limit'] = $row_limit = self::CHANNEL_MAX;
        $customFields = customer()->getId();
        $isPartner = customer()->isPartner();
        $data['is_partner'] = $isPartner;
        $data['isLogin'] = $customFields ? true : false;
        $data['login'] = url()->link('account/login');
        $data['url_product_view_more'] = url()->link('product/column');
        $route = request('route', 'common/home') ?? '';
        if ($route == 'common/home' || !$route) {
            $data['is_home'] = 1;
        } else {
            $data['is_home'] = 0;
        }
        $data['route'] = $route;
        $data['ticketIsShowSubmitButton'] = $model_account_ticket->isShowSubmitButton();
        $cacheKey = [__CLASS__, __FUNCTION__, $customFields, session('country')];
        // 有缓存且缓存不刷新的情况下使用
        if (cache()->has($cacheKey) && $cacheRefresh != 2) {
            [[$homePageOperationValidInfo, $menuInfo], $homeChannelData] = cache()->get($cacheKey);
        } else {
            // 展示首页分类
            [$homePageOperationValidInfo, $menuInfo] = $this->getHomeConfigCategoryList($cacheRefresh);
            // 获取首页频道数据
            $homeChannelData = $this->getHomeChannelData($setting, $customFields, $cacheRefresh);
            cache()->set($cacheKey, [[$homePageOperationValidInfo, $menuInfo], $homeChannelData], CacheTimeToLive::FIFTEEN_MINUTES + random_int(0, CacheTimeToLive::ONE_MINUTE));
        }
        // 实时判断feature stores的内容
        $storeInfos = $this->getBannersRightSellers();
        $data['isNewStores'] = $storeInfos['type'] == HomeChannelEnum::FEATURE_STORE ? 0 : 1;
        $data['homePageOperation'] = $homePageOperationValidInfo;
        $data['menuInfo'] = $menuInfo;
        $data['app_version'] = APP_VERSION;

        return array_merge($data, $homeChannelData);

    }

    public function getHomeChannelData(array $setting, ?int $customFields, int $cacheRefresh = 0)
    {
        /** @var \ModelExtensionModuleProductHome $model_extension_module_product_home */
        $model_extension_module_product_home = load()->model('extension/module/product_home');
        $data = [];
        $findProductList = [];
        $channelProductsData = [];

        $countryCode = session('country');
        $modules = HomeChannelEnum::getHomePageViewItems();
        $func = $this->getHomeChannelMethod($setting, $countryCode, $cacheRefresh);
        foreach ($modules as $key => $module) {
            $model = HomeChannelEnum::getChanelModelByValue($module);
            if ($module == HomeChannelEnum::FEATURE_STORE) {
                $model->setShowNum(4);
            }
            $res = call_user_func_array([$model, $func[$key]['method']], $func[$key]['param']);
            switch ($module) {
                case HomeChannelEnum::PRODUCT_RECOMMEND:
                case HomeChannelEnum::NEW_ARRIVALS:
                    if ($res && count($res) > self::CHANNEL_LIMIT) {
                        $findProductList = array_merge($findProductList, array_column($res, $func[$key]['column']));
                    }
                    $channelProductsData[$module] = $res;
                    break;
                case HomeChannelEnum::BEST_SELLERS:
                    // 格式形如 [$bestSellResults, $bestSellerEnd]
                    if ($res) {
                        [$bestSellResults, $bestSellerEnd] = $res;
                        if ($bestSellResults && count($bestSellResults) > self::CHANNEL_LIMIT) {
                            $findProductList = array_merge($findProductList, array_column($bestSellResults, $func[$key]['column']));
                        }
                        $channelProductsData[$module] = $bestSellResults;
                        $data['bestSellerEnd'] = $bestSellerEnd;
                    }
                    break;
                case HomeChannelEnum::WELL_STOCKED:
                    if ($res[$func[$key]['column']] && count($res[$func[$key]['column']]) > self::CHANNEL_LIMIT) {
                        $findProductList = array_merge($findProductList, $res[$func[$key]['column']]);
                    }
                    $channelProductsData[$module] = $res;
                    $data['wellStockEnd'] = $res['is_end'];
                    break;

                case HomeChannelEnum::PRICE_DROP:
                    if (in_array($countryCode, ['USA'])) {
                        [$bigPriceDrops, $bigPriceEnd] = $res;
                        $data['bigPriceEnd'] = $bigPriceEnd;
                        if ($bigPriceDrops && count($bigPriceDrops) > self::CHANNEL_LIMIT) {
                            $findProductList = array_merge($findProductList, array_column($bigPriceDrops, $func[$key]['column']));
                        }
                        $channelProductsData[$module] = $bigPriceDrops;
                    } else {
                        $channelProductsData[$module] = [];
                    }
                    break;
                case HomeChannelEnum::FEATURE_STORE:
                    $data['featuredStoresEnd'] = $res['is_end'];
                    if ($res['productIds'] && count($res['productIds']) > self::CHANNEL_LIMIT) {
                        $findProductList = array_merge($findProductList, $res[$func[$key]['column']]);
                    }
                    $channelProductsData[$module] = $res;
                    break;
            }

        }

        $findProductList = array_unique($findProductList);
        $allProductInfo = $model_extension_module_product_home->getHomeProductInfo($findProductList, $customFields, ['check-exist' => false]);
        $allProductInfo = array_combine(array_column($allProductInfo, 'product_id'), $allProductInfo);
        $channelData = $this->getHomeProductInfoCombine($allProductInfo, $channelProductsData, $setting);
        return array_merge($data, $channelData);

    }

    public function getHomeProductInfoCombine(array $allProductInfo, array $channelProductsData, array $setting): array
    {
        $data = [];
        $countryCode = session('country');
        foreach ($channelProductsData as $key => $items) {
            $data[HomeChannelEnum::getViewItems()[$key]] = [];
            switch ($key) {
                case HomeChannelEnum::FEATURE_STORE:
                    $featuredStoresInfo = $items;
                    if ($featuredStoresInfo) {
                        foreach ($featuredStoresInfo['data'] as $k => $products) {
                            $tmp = [];
                            foreach ($products['productIds'] as $product) {
                                if (isset($allProductInfo[$product])) {
                                    $tmp[] = $allProductInfo[$product];
                                }
                            }
                            $featuredStoresInfo['data'][$k]['productIds'] = $tmp;
                        }
                    }
                    $data[HomeChannelEnum::getViewItems()[$key]] = $featuredStoresInfo;
                    break;
                case HomeChannelEnum::PRICE_DROP:
                    $bigPriceDrops = $items;
                    $bigPriceDropsCount = 0;
                    if (in_array($countryCode, ['USA'])) {
                        if ($bigPriceDrops && count($bigPriceDrops) > self::CHANNEL_LIMIT) {
                            $tmp_product_id_list = array_column($bigPriceDrops, 'product_id');
                            foreach ($tmp_product_id_list as $product_id) {
                                if (isset($allProductInfo[$product_id]) && $bigPriceDropsCount < 5) {
                                    $bigPriceDropsCount++;
                                    $temp = $allProductInfo[$product_id];
                                    $data[HomeChannelEnum::getViewItems()[$key]][] = $temp;
                                }
                            }
                        }
                    }
                    break;
                case HomeChannelEnum::WELL_STOCKED:
                    $abundantInventorys = $items;
                    if ($abundantInventorys['productIds'] && count($abundantInventorys['productIds']) > self::CHANNEL_LIMIT) {
                        $tmp_product_id_list = $abundantInventorys['productIds'];
                        foreach ($tmp_product_id_list as $product_id) {
                            if (isset($allProductInfo[$product_id])) {
                                $temp = $allProductInfo[$product_id];
                                $data[HomeChannelEnum::getViewItems()[$key]][] = $temp;
                            }
                        }
                    }
                    break;
                case HomeChannelEnum::BEST_SELLERS:
                    $bestSellCount = 0;
                    $bestSellResults = $items;
                    if ($bestSellResults && count($bestSellResults) > self::CHANNEL_LIMIT) {
                        $tmp_product_id_list = array_column($bestSellResults, 'product_id');
                        $tmp_index = 0;
                        foreach ($tmp_product_id_list as $product_id) {
                            if (isset($allProductInfo[$product_id]) && $bestSellCount < (self::CHANNEL_MAX + 1)) {
                                $bestSellCount++;
                                $temp = $allProductInfo[$product_id];
                                if ($tmp_index < self::CHANNEL_LIMIT) {
                                    $temp['horn_mark'] = 'hot';//角标
                                    $tmp_index++;
                                }
                                $data[HomeChannelEnum::getViewItems()[$key]][] = $temp;
                            }
                        }
                    }
                    break;
                case HomeChannelEnum::NEW_ARRIVALS:
                    $newArrivalsResults = $items;
                    if ($newArrivalsResults && count($newArrivalsResults) > self::CHANNEL_LIMIT) {
                        $tmp_product_id_list = array_column($newArrivalsResults, 'product_id');
                        foreach ($tmp_product_id_list as $product_id) {
                            if (isset($allProductInfo[$product_id])) {
                                $temp = $allProductInfo[$product_id];
                                $data['newArrivals'][] = $temp;
                            }
                        }
                    }
                    break;
                case HomeChannelEnum::PRODUCT_RECOMMEND:
                    $sort_product = array_flip($setting['product']);
                    if (!empty($setting['product'])) {
                        $products_recommend = $items;
                        if ($products_recommend && count($products_recommend) > self::CHANNEL_LIMIT) {
                            $recommend_arr = [];
                            foreach ($products_recommend as $k => $v) {
                                if (isset($sort_product[$v['product_id']])) {
                                    $recommend_arr[$sort_product[$v['product_id']]] = $v['product_id'];
                                }
                            }
                            ksort($recommend_arr);
                            $tmp_index = 0;
                            foreach ($recommend_arr as $product_id) {
                                if (isset($allProductInfo[$product_id])) {
                                    $temp = $allProductInfo[$product_id];
                                    if ($tmp_index < self::CHANNEL_LIMIT) {
                                        $temp['horn_mark'] = 'featured';//角标
                                        $tmp_index++;
                                    }
                                    $data[HomeChannelEnum::getViewItems()[$key]][] = $temp;
                                }
                            }
                        }
                    }
                    break;
            }
        }
        return $data;

    }

    /**
     * 首页配置数据
     * @param int $cacheRefresh
     * @return array
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function getHomeConfigCategoryList($cacheRefresh = 0): array
    {
        $cacheKey = [__CLASS__, __FUNCTION__, session('country')];
        if (cache()->has($cacheKey) && $cacheRefresh != 2) {
            return cache()->get($cacheKey);
        }

        $homePageOperation = HomePageOperation::query()
            ->with(['homePageOperationValidDetails'])
            ->where([
                'type' => 0,
                'status' => 1,
                'is_delete' => 0,
                'country_id' => (int)CountryHelper::getCountryByCode(session('country')),
            ])
            ->orderBy('sort')
            ->get()
            ->toArray();

        $menuIds = [];
        $homePageOperationValidInfo = [];
        foreach ($homePageOperation as $item) {
            if (!$item['url']) {
                continue;
            }
            if ($item['image_menu_id']) {
                $menuIds[] = $item['image_menu_id'];
            }
            $details = [];
            foreach ($item['home_page_operation_valid_details'] as $children) {
                //
                if ($children['classify_type'] == 0) {
                    $children['url'] .= '&category_id=' . $item['operation_id'];
                }
                if ($children['image_menu_id']) {
                    $menuIds[] = $children['image_menu_id'];
                }

                if (!$children['url']) {
                    continue;
                }
                $details[] = $children;
            }
            $item['home_page_operation_valid_details'] = $details;
            $homePageOperationValidInfo[] = $item;
        }
        $menuIds = array_values(array_unique($menuIds));
        try {
            $menuInfo = RemoteApi::file()->getByMenuIds($menuIds);
        } catch (Exception $e) {
            $menuInfo = [];
        }
        cache()->set($cacheKey, [$homePageOperationValidInfo, $menuInfo], CacheTimeToLive::THREE_HOURS);
        return [$homePageOperationValidInfo, $menuInfo];
    }

    private function getHomeChannelMethod(array $setting, string $countryCode, int $cacheRefresh = 0): array
    {
        return [
            [
                'method' => 'recommendFiledHome',
                'param' => [$setting['product'], $countryCode],
                'column' => 'product_id',
            ],
            [
                'method' => 'getHomeNewArrivalsProductIds',
                'param' => [self::CHANNEL_MAX, $cacheRefresh],
                'column' => 'product_id',
            ],
            [
                'method' => 'getHomeBestSellersProductIds',
                'param' => [self::CHANNEL_MAX, $cacheRefresh],
                'column' => 'product_id',
            ],
            [
                'method' => 'getWellStockProductIds',
                'param' => [self::CHANNEL_MAX, $cacheRefresh],
                'column' => 'productIds',
            ],
            [
                'method' => 'getHomeDropPriceProductIds',
                'param' => [self::CHANNEL_MAX, $cacheRefresh],
                'column' => 'product_id',
            ],
            [
                'method' => 'getHomePageFeatureStoresData',
                'param' => [['each_number' => 2, 'hasRate' => true]],
                'column' => 'productIds',
            ],
        ];
    }


}
