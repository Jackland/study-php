<?php


namespace App\Repositories\Product\Channel\Module;

use App\Enums\Product\Channel\BestSellers as BestSellersEnums;
use App\Enums\Product\Channel\CacheTimeToLive;
use App\Enums\Product\Channel\ChannelType;
use App\Enums\Product\Channel\ProductChannelDataType;
use App\Enums\Product\ProductType;
use App\Helper\CountryHelper;
use App\Logging\Logger;
use App\Models\Link\ProductToCategory;
use App\Models\Product\Channel\ChannelParamConfigValue;
use App\Models\Product\ProductCrontab;
use ModelCommonCategory;
use Psr\SimpleCache\InvalidArgumentException;

class BestSellers extends BaseInfo
{

    /**
     * @throws InvalidArgumentException
     */
    public function getData(array $param): array
    {
        $categoryId = $param['category_id'];
        $page = $param['page'];
        $pageLimit = $this->getShowNum();
        [$this->productIds, $isEnd] = $this->getBestSellersProductIds($categoryId, $page, $pageLimit);
        return [
            'type' => ProductChannelDataType::PRODUCT,
            'data' => $this->productIds,
            'productIds' => $this->productIds,
            'is_end' => $isEnd,
        ];
    }

    /**
     * 获取当前best Seller limit 100的数据并缓存(获取分类的时候需要缓存2000个)
     * @param int $categoryId
     * @param int $pageLimit
     * @param int $cacheRefresh
     * @return array|mixed
     * @throws InvalidArgumentException
     */
    private function getBestSellersAllData(int $categoryId = 0, int $pageLimit = 100, int $cacheRefresh = 0): array
    {
        $cacheKey = [__CLASS__, __FUNCTION__, customer()->getId(), $categoryId, $pageLimit, session()->get('country')];
        if (cache()->has($cacheKey) && $cacheRefresh == 0) {
            $allData = cache()->get($cacheKey);
        } else {
            // 获取权重
            // 搜索排序值
            //近14天销售额参数
            $configValue = ChannelParamConfigValue::queryRead()->alias('cpcv')
                ->leftJoinRelations(['channelParamConfig as cpc'])
                ->where([
                    'cpc.status' => 1,
                    'cpcv.status' => 1,
                    'cpc.name' => BestSellersEnums::NAME,
                ])
                ->select(['cpcv.param_name', 'cpcv.param_value'])
                ->get()
                ->keyBy('param_name')
                ->toArray();
            $searchWeight = $configValue[BestSellersEnums::PARAM_SEARCH]['param_value'] ?? 0;
            $amount14 = $configValue[BestSellersEnums::PARAM_SALES_14]['param_value'] ?? 0;
            $builder = ProductCrontab::queryRead()->alias('pc')
                ->joinRelations(['customerPartnerToProduct as ctp', 'product as p'])
                ->leftjoinRelations(['productWeightConfig as pwc'])
                ->leftJoin('oc_customer as c', 'c.customer_id', 'ctp.customer_id')
                ->where([
                    'p.status' => 1,
                    'p.is_deleted' => 0,
                    'p.buyer_flag' => 1,
                    'p.part_flag' => 0,
                    'p.product_type' => ProductType::NORMAL,
                    'c.country_id' => CountryHelper::getCountryByCode(session()->get('country')),
                    'c.status' => 1,
                ])
                ->where([
                    ['p.quantity', '>', 0],
                    ['pc.amount_14', '>', 0],
                ])
                ->whereNotIn('p.product_id', $this->channelRepository->delicacyManagementProductId((int)customer()->getId()))
                ->whereIn('c.customer_id', $this->channelRepository->getAvailableSellerId())
                ->whereNotNull('ctp.customer_id')
                ->whereNotNull('p.product_id')
                ->when($categoryId > 0, function ($q) use ($categoryId) {
                    /** @var ModelCommonCategory $cateModel */
                    $cateModel = load()->model('common/category');
                    $q->leftJoin('oc_product_to_category as ptc', 'ptc.product_id', '=', 'p.product_id');
                    $q->whereIn('ptc.category_id', array_column($cateModel->getSonCategories($categoryId), 'category_id'));
                    $q->whereIn('pc.product_id', array_column($this->getBestSellersAllData(), 'product_id'));
                    return $q;
                })
                ->when($categoryId == -1, function ($q) use ($categoryId) {
                    $key = $this->channelRepository->getChannelCategoryCacheKey(ChannelType::BEST_SELLERS);
                    if (!cache()->has($key) || !is_array(cache($key))) {
                        $results = $this->getBestSellersCategory();
                        $this->channelRepository->getChannelCategory($results, ChannelType::BEST_SELLERS);
                    }
                    $categoryIdList = cache($key);
                    $q->leftJoin('oc_product_to_category as ptc', 'ptc.product_id', '=', 'p.product_id');
                    $q->whereRaw(" (ptc.category_id in ( " . implode(',', $categoryIdList) . ") or ptc.category_id is null) ");
                    $q->whereIn('pc.product_id', array_column($this->getBestSellersAllData(), 'product_id'));
                    return $q;
                })
                ->selectRaw("( if(log(10,pc.amount_14)< 3,0,ifnull((1-1/(log(10,pc.amount_14) -2))*$amount14,0)) + ifnull(pwc.custom_weight * $searchWeight,0)) as rate,pc.product_id,ctp.customer_id,pc.amount_14,pwc.custom_weight")
                ->groupBy('pc.product_id')
                ->orderByRaw('rate desc')
                ->limit($pageLimit);
            //logger::channelProducts(get_complete_sql($builder), 'notice');
            $allData = $builder->get()->toArray();
            cache()->set($cacheKey, $allData, CacheTimeToLive::FIFTEEN_MINUTES);
        }
        return $allData;
    }

    /**
     * channel best sellers 获取
     * @param int $categoryId
     * @param int $page
     * @param int $pageLimit
     * @return array
     * @throws InvalidArgumentException
     */
    private function getBestSellersProductIds($categoryId, int $page, int $pageLimit): array
    {
        $allData = collect($this->getBestSellersAllData((int)$categoryId));
        // 排序返回，判断是否是最后一页
        $total = $allData->count();
        $isEnd = $page * $pageLimit > $total ? 1 : 0;
        $ret = $allData->forPage($page, $pageLimit)->pluck('product_id')->toArray();
        return [$ret, $isEnd];
    }

    /**
     * 首页获取top n best seller
     * @param int $pageLimit
     * @param int $cacheRefresh
     * @return array
     * @throws InvalidArgumentException
     */
    public function getHomeBestSellersProductIds(int $pageLimit, int $cacheRefresh = 0): array
    {
        $allData = collect($this->getBestSellersAllData(0, 100, $cacheRefresh));
        if (count($allData->groupBy('customer_id')->take($pageLimit)->pluck(0)->toArray()) < $pageLimit) {
            $isEnd = 0;
            if ($allData->count() <= $pageLimit) {
                $isEnd = 1;
            }
            return [$allData->toArray(), $isEnd];
        }
        // 处理top n
        // Best Sellers：每个Seller取1个产品，频道页前N个产品（N取决于显示产品的数量），不足N个时，每个Seller1个条件去除
        return [$allData->groupBy('customer_id')->take($pageLimit)->pluck(0)->toArray(), 0];

    }

    /**
     * 获取当前channel的分类
     * @return array
     * @throws InvalidArgumentException
     */
    public function getBestSellersCategory(): array
    {
        // 获取当前产品的所有分类
        $allData = collect($this->getBestSellersAllData());
        $productIds = $allData->pluck('product_id')->toArray();
        // 获取当前无产品分类的产品
        $builder = ProductToCategory::queryRead()->alias('ptc')
            ->whereIn('ptc.product_id', $productIds)
            ->selectRaw('ptc.category_id,ptc.product_id');

        $allCategoryList = $builder->get()->toArray();

        $hasCategoryProducts = $builder->pluck('product_id')->toArray();
        $diff = array_diff($productIds, $hasCategoryProducts);
        foreach ($diff as $key => $value) {
            $allCategoryList[] = [
                'category_id' => -1,
                'product_id' => $value,
            ];
        }
        return $allCategoryList;
    }
}
