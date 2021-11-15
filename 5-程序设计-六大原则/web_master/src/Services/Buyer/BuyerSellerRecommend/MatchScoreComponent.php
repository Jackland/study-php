<?php

namespace App\Services\Buyer\BuyerSellerRecommend;

use App\Components\Traits\RequestCachedDataTrait;
use App\Enums\Order\OcOrderStatus;
use App\Models\Link\CustomerPartnerToProduct;
use App\Models\Link\ProductToCategory;
use App\Models\Order\OrderProduct;
use App\Repositories\Product\CategoryRepository;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class MatchScoreComponent
{
    use RequestCachedDataTrait;

    private $buyerPool;
    private $sellerPool;

    private $buyerCategoryIds;
    private $sellerCategoryIds;

    public function __construct(BuyerPool $buyerPool, SellerPool $sellerPool)
    {
        $this->buyerPool = $buyerPool;
        $this->sellerPool = $sellerPool;

        $this->buyerCategoryIds = $this->calculateBuyerCategoryIds();
        $this->sellerCategoryIds = $this->calculateSellerCategoryIds();
    }

    /**
     * 获取 buyer 和 seller 的匹配度
     * @param int $buyerId
     * @param int $sellerId
     * @return int
     */
    public function getBuyerSellerMatchScore(int $buyerId, int $sellerId): int
    {
        $buyerCategoryIds = $this->buyerCategoryIds[$buyerId] ?? [];
        $sellerCategoryIds = $this->sellerCategoryIds[$sellerId] ?? [];

        if (count($buyerCategoryIds) <= 0 || count($sellerCategoryIds) <= 0) {
            return 0;
        }

        // 匹配度计算公式：Buyer与Seller相同的品类数量/Seller上架产品的品类数量
        $score = count(array_intersect($buyerCategoryIds, $sellerCategoryIds)) / count($sellerCategoryIds) * 100;
        if ($score > 98) {
            // 匹配度最高 98
            $score = 98;
        }
        return intval($score);
    }

    /**
     * 计算所有 buyer 的品类
     * @return array [$buyerId => [$categoryId, $categoryId, ]]
     */
    protected function calculateBuyerCategoryIds()
    {
        $data = OrderProduct::query()->alias('op')
            ->leftJoinRelations(['order as o', 'product as p'])
            ->select(['o.customer_id', 'p.product_id'])
            ->whereIn('o.customer_id', $this->buyerPool->getBuyerIds())
            ->where('o.order_status_id', OcOrderStatus::COMPLETED) // 已完成的采购单
            ->where('o.date_modified', '>', Carbon::now()->hour(0)->minute(0)->second(0)->subMonth(3)) // 3个月内
            ->groupBy(['o.customer_id', 'p.product_id'])
            ->get();
        return $this->solveCategoryGroupByCustomerId($data);
    }

    /**
     * 计算所有 seller 的品类
     * @return array [$sellerId => [$categoryId, $categoryId, ]]
     */
    protected function calculateSellerCategoryIds()
    {
        // seller 上架的产品品类
        $data = CustomerPartnerToProduct::query()->alias('a')
            ->leftJoinRelations('product as b')
            ->select(['a.product_id', 'a.customer_id'])
            ->whereIn('a.customer_id', $this->sellerPool->getSellerIds())
            ->where('b.is_deleted', 0) // 未被删除
            ->where('b.status', 1) // 可用
            ->where('b.buyer_flag', 1) // 可单独售卖的
            ->groupBy(['a.product_id', 'a.customer_id'])
            ->get();
        return $this->solveCategoryGroupByCustomerId($data);
    }

    /**
     * 处理产品分类，按用户分组
     * @param Collection $data [['product_id' => '', 'customer_id' => '']]
     * @return array [$customerId => [$categoryId, $categoryId, ]]
     */
    protected function solveCategoryGroupByCustomerId(Collection $data)
    {
        $categoryIds = ProductToCategory::query()
            ->select(['category_id', 'product_id'])
            ->whereIn('product_id', $data->pluck('product_id')->unique()->values()->all())
            ->get();
        $keyByProduct = [];
        foreach ($categoryIds as $category) {
            $keyByProduct[$category->product_id][] = $category->category_id;
        }

        $keyByCustomer = [];
        foreach ($data as $index => $item) {
            if (!isset($keyByProduct[$item['product_id']])) {
                continue;
            }
            if (!isset($keyByCustomer[$item['customer_id']])) {
                $keyByCustomer[$item['customer_id']] = [];
            }
            $keyByCustomer[$item['customer_id']] = array_merge($keyByCustomer[$item['customer_id']], $keyByProduct[$item['product_id']]);
        }
        return array_map(function ($ids) {
            return array_unique($this->qualityCategories(array_values(array_unique($ids))));
        }, $keyByCustomer);
    }

    private $_cachedCategoryIds = [];

    /**
     * 处理类目
     * @param array $categoryIds
     * @return array
     */
    protected function qualityCategories(array $categoryIds)
    {
        $cachedCategoryIds = $this->_cachedCategoryIds;

        $needFetchIds = [];
        $existIds = [];
        foreach ($categoryIds as $categoryId) {
            if (array_key_exists($categoryId, $cachedCategoryIds)) {
                $existIds[$categoryId] = $cachedCategoryIds[$categoryId];
                continue;
            }
            $needFetchIds[] = $categoryId;
        }

        if ($needFetchIds) {
            // 此处为实际查询获取二级类目的方法
            $result = app(CategoryRepository::class)->getMaxLevelCategoryIds($needFetchIds, 2);
            $cachedCategoryIds = $cachedCategoryIds + $result;
            $this->_cachedCategoryIds = $cachedCategoryIds;
            $result = $existIds + $result;
        } else {
            $result = $existIds;
        }

        // 为 0 的表示分类不存在
        return array_values(array_filter($result, function ($id) {
            return $id > 0;
        }));
    }
}
