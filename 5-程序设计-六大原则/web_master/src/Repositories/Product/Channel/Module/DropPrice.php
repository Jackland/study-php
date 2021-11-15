<?php

namespace App\Repositories\Product\Channel\Module;

use App\Enums\Product\Channel\CacheTimeToLive;
use App\Enums\Product\Channel\ChannelType;
use App\Enums\Product\Channel\DropPrice as DropPriceEnums;
use App\Enums\Product\Channel\ProductChannelDataType;
use App\Enums\Product\ProductType;
use App\Models\DelicacyManagement\SellerPriceHisotry;
use App\Models\Link\ProductToCategory;
use App\Models\Product\Channel\ChannelParamConfigValue;
use App\Models\Product\ProductCrontab;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Query\Expression;
use ModelCommonCategory;
use Psr\SimpleCache\InvalidArgumentException;

class DropPrice extends BaseInfo
{

    /**
     * @throws InvalidArgumentException
     */
    public function getData(array $param): array
    {
        $categoryId = $param['category_id'];
        $page = $param['page'];
        $pageLimit = $this->getShowNum();
        [$this->productIds, $isEnd] = $this->getDropPriceProductIds($categoryId, $page, $pageLimit);
        return [
            'type' => ProductChannelDataType::PRODUCT,
            'data' => $this->productIds,
            'productIds' => $this->productIds,
            'is_end' => $isEnd,
        ];
    }

    /**
     * 获取drop price的排序数据
     * @param int $categoryId
     * @param int $cacheRefresh
     * @return array
     * @throws InvalidArgumentException
     */
    private function getDropPriceAllData(int $categoryId = 0, int $cacheRefresh = 0): array
    {
        // 近14天内以降价幅度及降价时间计算，排序值最大的前100个产品；
        // 每个店铺展示的产品不超过20个，即如有21个仅取非同款的前20个；
        $cacheKey = [__CLASS__, __FUNCTION__, customer()->getId(), $categoryId, session()->get('country')];
        if (cache()->has($cacheKey) && $cacheRefresh == 0) {
            $retItems = cache()->get($cacheKey);
        } else {
            // 搜索排序值
            // 降价幅度
            // 降价天数
            $configValue = ChannelParamConfigValue::query()->alias('cpcv')
                ->leftJoinRelations(['channelParamConfig as cpc'])
                ->where([
                    'cpc.status' => 1,
                    'cpcv.status' => 1,
                    'cpc.name' => DropPriceEnums::NAME,
                ])
                ->select(['cpcv.param_name', 'cpcv.param_value'])
                ->get()
                ->keyBy('param_name')
                ->toArray();
            $searchWeight = $configValue[DropPriceEnums::PARAM_SEARCH]['param_value'] ?? 0;
            $dropPriceDays = $configValue[DropPriceEnums::PARAM_DROP_PRICE_DAYS]['param_value'] ?? 0;
            $dropPriceRange = $configValue[DropPriceEnums::PARAM_DROP_PRICE_PRICE]['param_value'] ?? 0;
            $builder = ProductCrontab::query()->alias('pc')
                ->leftJoinRelations(['product as p', 'productWeightConfig as pwc'])
                ->leftJoin('oc_customerpartner_to_product as ctp', 'ctp.product_id', 'p.product_id')
                ->leftJoin('oc_customer as c', 'c.customer_id', 'ctp.customer_id')
                ->whereIn('c.customer_id', $this->channelRepository->getAvailableSellerId())
                ->where([
                    'p.status' => 1,
                    'p.is_deleted' => 0,
                    'p.buyer_flag' => 1,
                    'p.product_type' => ProductType::NORMAL,
                    'c.country_id' => AMERICAN_COUNTRY_ID,
                    'c.status' => 1,
                ])
                ->where([
                    ['p.quantity', '>', 0],
                    ['p.part_flag', '=', 0],
                ])
                //->whereNotIn('p.product_id', $this->channelRepository->getUnavailableProductIds())
                ->whereNotIn('p.product_id', $this->channelRepository->delicacyManagementProductId((int)customer()->getId()))
                ->where('pc.drop_price_rate_14', '>', 0)
                ->when($categoryId > 0, function ($q) use ($categoryId) {
                    /** @var ModelCommonCategory $cateModel */
                    $cateModel = load()->model('common/category');
                    $q->leftJoin('oc_product_to_category as ptc', 'ptc.product_id', '=', 'p.product_id');
                    $q->whereIn('ptc.category_id', array_column($cateModel->getSonCategories($categoryId), 'category_id'));
                    $q->whereIn('pc.product_id', array_column($this->getDropPriceAllData(), 'product_id'));
                    return $q;
                })
                ->when($categoryId == -1, function ($q) use ($categoryId) {
                    $key = $this->channelRepository->getChannelCategoryCacheKey(ChannelType::DROP_PRICE);
                    if (!cache()->has($key) || !is_array(cache($key))) {
                        $results = $this->getDropPriceCategory();
                        $this->channelRepository->getChannelCategory($results, ChannelType::DROP_PRICE);
                    }

                    $categoryIdList = cache($key);
                    $q->leftJoin('oc_product_to_category as ptc', 'ptc.product_id', '=', 'p.product_id');
                    $q->whereRaw(" (ptc.category_id in ( " . implode(',', $categoryIdList) . ") or ptc.category_id is null) ");
                    $q->whereIn('pc.product_id', array_column($this->getDropPriceAllData(), 'product_id'));
                    return $q;
                })
                ->where('pc.seller_price_time', '>', Carbon::now()->subDays(14)->format('Y-m-d H:i:s'))
                ->select(['p.product_id', 'pc.seller_price_time', 'pc.drop_price_rate_14', 'c.customer_id'])
                ->selectRaw("(ifnull(drop_price_rate_14 * $dropPriceRange,0) + ifnull(pwc.custom_weight * $searchWeight,0) + ifnull((1- DATEDIFF(NOW(),pc.seller_price_time)/14) * $dropPriceDays,0)) as rate")
                ->orderBy('rate', 'desc');
            $builder = db(new Expression('(' . get_complete_sql($builder) . ') as t'))
                ->leftJoin('oc_product_associate as opa', 'opa.product_id', 't.product_id')
                ->select('t.product_id', 't.seller_price_time', 't.customer_id', 't.rate')
                ->selectRaw('IFNULL(GROUP_CONCAT(DISTINCT opa.associate_product_id ORDER BY opa.associate_product_id), t.product_id) AS p_associate')
                ->orderBy('t.rate', 'desc')
                ->groupBy(['t.product_id']);

            $allData = db(new Expression('(' . get_complete_sql($builder) . ') as s'))
                ->groupBy(['s.p_associate'])
                ->orderBy('s.rate', 'desc')
                ->get()
                ->map(function ($v) {
                    return (array)$v;
                })
                ->toArray();
            $retItems = [];
            foreach ($allData as $item) {
                if (isset($ret[$item['customer_id']]) && count($ret[$item['customer_id']]) >= 20) {
                    continue;
                } else {
                    $ret[$item['customer_id']][] = $item['product_id'];
                    $retItems[] = $item;
                }
            }
            cache()->set($cacheKey, $retItems, CacheTimeToLive::FIFTEEN_MINUTES);
        }

        return $retItems;
    }

    /**
     * @param int $categoryId
     * @param int $page
     * @param int $pageLimit
     * @return array
     * @throws Exception|InvalidArgumentException
     */
    private function getDropPriceProductIds($categoryId, int $page, int $pageLimit): array
    {
        $allData = collect($this->getDropPriceAllData($categoryId));
        // 排序返回，判断是否是最后一页
        $total = $allData->count();
        $isEnd = $page * $pageLimit > $total ? 1 : 0;
        $ret = $allData->forPage($page, $pageLimit)->pluck('product_id')->toArray();
        return [$ret, $isEnd];
    }

    /**
     * @param int $pageLimit
     * @param int $cacheRefresh
     * @return array
     * @throws InvalidArgumentException
     */
    public function getHomeDropPriceProductIds(int $pageLimit, int $cacheRefresh = 0): array
    {
        $allData = collect($this->getDropPriceAllData(0, $cacheRefresh));
        if (count($allData->groupBy('customer_id')->take($pageLimit)->pluck(0)->toArray()) < $pageLimit) {
            $isEnd = 0;
            if ($allData->count() <= $pageLimit) {
                $isEnd = 1;
            }
            return [$allData->toArray(), $isEnd];
        }
        // 处理top n
        // Price Drop：每个Selle取1个产品，频道页前N个产品（N取决于显示产品的数量），不足N个时，每个Seller1个条件去除
        return [$allData->groupBy('customer_id')->take($pageLimit)->pluck(0)->toArray(), 0];

    }

    /**
     * 获取当前drop price分类
     * @return array
     * @throws InvalidArgumentException
     */
    public function getDropPriceCategory(): array
    {
        $allData = collect($this->getDropPriceAllData());
        $productIds = $allData->pluck('product_id')->toArray();
        // 获取当前无产品分类的产品
        $builder = ProductToCategory::query()->alias('ptc')
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
