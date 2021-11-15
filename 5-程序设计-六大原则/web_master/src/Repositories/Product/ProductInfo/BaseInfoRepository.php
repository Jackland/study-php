<?php

namespace App\Repositories\Product\ProductInfo;

use App\Components\Traits\RequestCachedDataTrait;
use App\Models\Product\Product;

class BaseInfoRepository
{
    use RequestCachedDataTrait;
    use Traits\BaseInfoRepository\CustomerDelicacySolveTrait;
    use Traits\BaseInfoRepository\LoadRelationsSupportTrait;
    use Traits\BaseInfoRepository\CustomerSupportTrait;

    private $productIds;

    public function __construct(array $ids)
    {
        $this->productIds = $ids;
    }

    private $basedCustomerId;

    /**
     * @deprecated 使用 withCustomerId 代替
     */
    public function customerBased($customerId): self
    {
        return $this->withCustomerId($customerId);
    }

    /**
     * 基于某个用户
     * @param int|string|null $customerId
     * @return BaseInfoRepository
     */
    public function withCustomerId($customerId): self
    {
        if (!$customerId) {
            return $this;
        }

        $new = clone $this;
        $new->basedCustomerId = (int)$customerId;

        return $new;
    }

    /**
     * 需要考虑精细化价格
     * @param int|string|null $buyerId
     * @return $this
     * @deprecated 移除，默认自动处理
     */
    public function withDelicacyManagementPrice($buyerId = null): self
    {
        return $this;
    }

    /**
     * 检查价格是否可见
     * @param int|string|null $customerId
     * @return $this
     * @deprecated 移除，默认自动处理
     */
    public function withPriceVisible($customerId = null): self
    {
        return $this;
    }

    /**
     * 检查库存是否可见
     * @param int|string|null $customerId
     * @return $this
     * @deprecated 移除，默认自动处理
     */
    public function withQtyVisible($customerId = null): self
    {
        return $this;
    }

    private $availableOnly = true;
    private $unavailableContainDeleted = true;

    /**
     * 允许查询出不可用的，默认只查可用的
     * 可用的条件为：is_deleted=0 (未删除), buyer_flag=1 (可单独售卖), status=1 (上架)
     * @see BaseInfo::AVAILABLE_CONDITION
     * @param bool $containDeleted 设为 false 时不包含已删除的
     * @return $this
     */
    public function withUnavailable(bool $containDeleted = true): self
    {
        $new = clone $this;
        $new->availableOnly = false;
        $new->unavailableContainDeleted = $containDeleted;

        return $new;
    }

    private $quantityGreaterThan = false;

    /**
     * 仅包含上架库存大于某个数量的
     * @param int $count
     * @return $this
     */
    public function withQuantityGreaterThan(int $count = 0): self
    {
        $new = clone $this;
        $new->quantityGreaterThan = $count;

        return $new;
    }

    /**
     * @return $this
     * @deprecated 移除，已经会自动加载
     * 需要 tag 标签
     */
    public function withTags(): self
    {
        return $this;
    }

    /**
     * 需要产品分类
     * @return $this
     * @deprecated 移除，已经会自动加载
     */
    public function withCategories(): self
    {
        return $this;
    }

    /**
     * @return array|BaseInfo[]
     */
    public function getInfos(): array
    {
        $query = Product::query()
            ->whereIn('product_id', $this->productIds);
        // withUnavailable
        if ($this->availableOnly) {
            $condition = BaseInfo::AVAILABLE_CONDITION;
            if (!$this->unavailableContainDeleted) {
                unset($condition['is_deleted']);
            }
            $query->where($condition);
        }
        // withQuantityGreaterThan
        if (is_int($this->quantityGreaterThan)) {
            $query->where('quantity', '>', $this->quantityGreaterThan);
        }
        $products = $query->get()->keyBy('product_id');
        $infos = [];
        foreach ($this->productIds as $productId) {
            // 循环 $this->productIds 而非 $products 是为了保持返回的产品顺序和原来一致
            if (!isset($products[$productId])) {
                continue;
            }
            $model = $products[$productId];
            $info = new BaseInfo($model);
            $infos[$info->id] = $info;
        }

        // 处理精细化情况
        $infos = $this->solveBaseInfosWithDelicacy($infos);
        // 设置 loadRelations 支持
        $this->supportLoadRelations($infos, $products);
        // 设置 customer 支持
        $this->supportCustomer($infos, $this->basedCustomerId);

        return $infos;
    }
}
