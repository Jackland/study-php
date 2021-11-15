<?php

use App\Repositories\Common\HomeRepository;
use App\Repositories\Product\Channel\Module\BestSellers;
use App\Repositories\Product\Channel\Module\DropPrice;
use App\Repositories\Product\Channel\Module\FeaturedStores;
use App\Repositories\Product\Channel\Module\NewArrivals;
use App\Repositories\Product\Channel\Module\WellStockedSearch;

/**
 * Class ControllerExtensionModuleFeatured
 * @property ModelAccountTicket $model_account_ticket
 * @property ModelCatalogProduct $model_catalog_product
 * @property ModelCatalogProductPrice $model_catalog_ProductPrice
 * @property ModelCatalogProductColumn $model_catalog_product_column
 * @property ModelExtensionModuleProductHome $model_extension_module_product_home
 * @property ModelToolSort $model_tool_sort
 */
class ControllerExtensionModuleFeatured extends Controller
{
    /**
     * 首页频道楼层
     * @param array $setting
     * @return string
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function index($setting)
    {
        $route = request('route', 'common/home');
        $cacheRefresh = request('_cache_refresh',0);
        $data = app(HomeRepository::class)->getFeatureData($setting,$cacheRefresh);
        $data['route'] = $route;
        return $this->load->view('extension/module/featured', $data);
    }
}
