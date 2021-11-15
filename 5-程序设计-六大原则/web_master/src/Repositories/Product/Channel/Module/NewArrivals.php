<?php

namespace App\Repositories\Product\Channel\Module;

use App\Enums\Product\Channel\CacheTimeToLive;
use App\Enums\Product\Channel\ProductChannelDataType;
use App\Enums\Product\ProductType;
use App\Helper\CountryHelper;
use App\Models\Link\ProductToCategory;
use App\Models\Product\Channel\HomePageConfig;
use App\Models\Product\Product;
use App\Models\Product\ProductExts;
use Exception;
use Illuminate\Database\Query\Expression;
use ModelCatalogProductColumn;

class NewArrivals extends BaseInfo
{

    /**
     * new arrivals下拉加载数据
     * @param array $param
     * @return array
     * @throws Exception
     */
    public function getData(array $param): array
    {
        $categoryId = $param['category_id'];
        $page = $param['page'];
        $pageLimit = $this->getShowNum();
        [$this->productIds, $isEnd] = $this->getNewArrivalsProductIds($categoryId, $page, $pageLimit);
        return [
            'type' => ProductChannelDataType::PRODUCT,
            'data' => $this->productIds,
            'productIds' => $this->productIds,
            'is_end' => $isEnd,
        ];
    }

    /**
     * 产品取值：按产品的入库时间倒序，取出Seller不同的4个参与复杂交易的产品；取出2个Seller不同的无复杂交易的产品；若复杂交易的不足4个，有几个取几个，剩余的取非复杂交易的产品补充； 以上产品按照入库时间倒序展示
     * @param int $pageLimit
     * @param int $cacheRefresh
     * @return array
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function getHomeNewArrivalsProductIds(int $pageLimit = 6, int $cacheRefresh = 0): array
    {
        $cacheKey = [__CLASS__, __FUNCTION__, customer()->getId(), session()->get('country')];
        if (cache()->has($cacheKey) && $cacheRefresh == 0) {
            return cache($cacheKey);
        }

        $data = HomePageConfig::query()
            ->where(['type_id' => \App\Enums\Product\Channel\HomePageConfig::NEW_ARRIVALS,
                'country_id' => CountryHelper::getCountryByCode(session()->get('country')),
            ])
            ->value('content');
        if(!$data){
            return [];
        }
        $data = collect(json_decode($data));
        $complexTransaction = collect(array_diff($data['complexTransaction'],$this->channelRepository->delicacyManagementProductId((int)customer()->getId())));
        $commonTransaction = collect(array_diff($data['commonTransaction'],$this->channelRepository->delicacyManagementProductId((int)customer()->getId())));
        //dd($complexTransaction,$commonTransaction);
        if($complexTransaction->count() < ($pageLimit / 3 * 2)){
            $tempComplex = $complexTransaction->take($pageLimit / 3 * 2)->toArray();
            $tempCommon = $commonTransaction->take(($pageLimit - count($tempComplex)) )->toArray();
        }else{
            // 4:2 配置
            $tempComplex = $complexTransaction->take($pageLimit / 3 * 2)->toArray();
            $tempCommon = $commonTransaction->take($pageLimit / 3 )->toArray();
        }
        $productList = array_values(array_merge($tempComplex,$tempCommon));
        return ProductExts::queryRead()
            ->whereIn('product_id',$productList)
            ->orderByDesc('receive_date')
            ->get('product_id')
            ->toArray();
    }

    /**
     * @param int $categoryId
     * @param int $page
     * @param int $pageLimit
     * @return array
     * @throws Exception
     */
    private function getNewArrivalsProductIds($categoryId, int $page, int $pageLimit): array
    {
        // 此处逻辑不进行改动
        /** @var ModelCatalogProductColumn $model_catalog_product_column */
        $model_catalog_product_column = load()->model('catalog/product_column');
        $allData = collect($model_catalog_product_column->newArrivalsChannelData($categoryId));
        // 排序返回，判断是否是最后一页
        $total = $allData->count();
        $isEnd = $page * $pageLimit > $total ? 1 : 0;
        $ret = $allData->forPage($page, $pageLimit)->pluck('product_id')->toArray();
        return [$ret, $isEnd];
    }

    /**
     * @throws Exception
     */
    public function getNewArrivalsCategory(): array
    {
        /** @var ModelCatalogProductColumn $model_catalog_product_column */
        $model_catalog_product_column = load()->model('catalog/product_column');
        $allData = collect($model_catalog_product_column->newArrivalsChannelData(0));
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
