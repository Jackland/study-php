<?php

namespace App\Repositories\Product\ProductInfo\Traits\BaseInfoRepository;

use App\Enums\Product\ProductStatus;
use App\Models\DelicacyManagement\DelicacyManagement;
use App\Models\DelicacyManagement\DelicacyManagementGroup;
use App\Models\Link\CustomerPartnerToProduct;
use App\Models\Product\Product;
use App\Repositories\Product\ProductInfo\BaseInfo;
use Carbon\Carbon;

/**
 * BaseInfoRepository 针对 customer 的精细化逻辑
 */
trait CustomerDelicacySolveTrait
{
    /**
     * 根据精细化处理一下数据
     * @param array|BaseInfo[] $infos
     */
    protected function solveBaseInfosWithDelicacy(array $infos): array
    {
        if (!$this->basedCustomerId) {
            // 未配置 customer 时不需要处理
            return $infos;
        }
        $customerId = $this->basedCustomerId;
        $productIds = array_keys($infos);
        // 检查产品精细化不可见的情况
        $data = $this->getProductVisibleByCustomerId($customerId, $productIds);
        foreach ($data as $productId => $isVisible) {
            if ($isVisible) {
                // 产品精细化可见的不处理
                continue;
            }
            if ($this->availableOnly) {
                // 仅需要可用的情况下直接移除精细化不不可见的产品
                $infos[$productId] = null;
            } else {
                $infos[$productId]->setDelicacyProductVisible($isVisible);
            }
        }
        // 设置精细化价格
        $data = $this->getDelicacyManagementPricesByBuyerId($customerId, $productIds);
        foreach ($data as $productId => $price) {
            if (!isset($infos[$productId])) {
                continue;
            }
            $infos[$productId]->setDelicacyPrice($price['current_price']);
            if (!$price['is_effect']) {
                // 未生效的标记未来价格
                $infos[$productId]->setDelicacyFuturePrice($price['future_price']);
            }
        }
        // 设置精细化价格是否可见
        $data = $this->getPriceVisibleByCustomerId($customerId, $productIds);
        foreach ($data as $productId => $isVisible) {
            if (!isset($infos[$productId])) {
                continue;
            }
            $infos[$productId]->setDelicacyPriceVisible($isVisible);
        }
        // 设置精细化库存是否可见
        $data = $this->getQtyVisibleByCustomerId($customerId, $productIds);
        foreach ($data as $productId => $isVisible) {
            if (!isset($infos[$productId])) {
                continue;
            }
            $infos[$productId]->setDelicacyQtyVisible($isVisible);
        }

        return array_filter($infos);
    }

    /**
     * 获取精细化价格
     * @param int $buyerId
     * @param array $productIds
     * @return array [$productId => ['current_price' => 1, 'future_price' => 1, 'is_effect' => true]]
     */
    private function getDelicacyManagementPricesByBuyerId(int $buyerId, array $productIds): array
    {
        $models = DelicacyManagement::query()
            ->whereIn('product_id', $productIds)
            ->where('buyer_id', $buyerId)
            ->where('product_display', 1)
            ->where('expiration_time', '>', Carbon::now())
            ->get();
        $ret = [];
        foreach ($models as $model) {
            $ret[$model->product_id] = [
                'current_price' => $model->current_price, // 当前价格，精细化未生效前为原价
                'future_price' => $model->price, // 精细化未来价格
                'is_effect' => (bool)$model->is_update, // 精细化是否已生效
            ];
        }
        return $ret;
    }

    /**
     * 获取产品对 customer 是否可见
     * @param int $customerId
     * @param array $productIds
     * @return array [$productId => $bool]
     */
    private function getProductVisibleByCustomerId(int $customerId, array $productIds): array
    {
        $ret = $this->getProductInfoVisibleByCustomerId($customerId, $productIds);
        return $ret['product'];
    }

    /**
     * 获取产品价格对 customer 是否可见
     * @param int $customerId
     * @param array $productIds
     * @return array [$productId => $bool]
     */
    private function getPriceVisibleByCustomerId(int $customerId, array $productIds): array
    {
        $ret = $this->getProductInfoVisibleByCustomerId($customerId, $productIds);
        return $ret['price'];
    }

    /**
     * 获取产品库存对 customer 是否可见
     * @param int $customerId
     * @param array $productIds
     * @return array [$productId => $bool]
     */
    private function getQtyVisibleByCustomerId(int $customerId, array $productIds): array
    {
        $ret = $this->getProductInfoVisibleByCustomerId($customerId, $productIds);
        return $ret['qty'];
    }

    /**
     * 获取产品价格和库存对 customer 是否可见
     * @param int $customerId
     * @param array $productIds
     * @return array ['product' => [$productId => $bool], 'price' => [$productId => $bool], 'qty' => [$productId => $bool]]
     */
    private function getProductInfoVisibleByCustomerId(int $customerId, array $productIds): array
    {
        $cacheKey = [__CLASS__, __FUNCTION__, func_get_args(), 'v1'];
        $cacheData = $this->getRequestCachedData($cacheKey);
        if ($cacheData !== null) {
            return $cacheData;
        }

        $productData = [];
        $priceData = [];
        $qtyData = [];
        foreach ($productIds as $productId) {
            $productData[$productId] = false;
            $priceData[$productId] = false;
            $qtyData[$productId] = false;
        }
        $isSeller = customer()->isPartner();
        if ($isSeller && customer()->getId() == $customerId) {
            // seller 自己对自己的产品均可见
            $ids = CustomerPartnerToProduct::query()
                ->select('product_id')
                ->whereIn('product_id', $productIds)
                ->where('customer_id', $customerId)
                ->get()->pluck('product_id')->toArray();
            foreach ($ids as $id) {
                $productData[$id] = true;
                $priceData[$id] = true;
                $qtyData[$id] = true;
            }
            // 后续的排查仅需要排查非 seller 自己的
            $productIds = array_diff($productIds, $ids);
        }
        if (!$isSeller && $productIds) {
            // 产品精细化不可见
            $ids = DelicacyManagement::query()
                ->whereIn('product_id', $productIds)
                ->where('buyer_id', $customerId)
                ->where('product_display', 0)
                ->where('expiration_time', '>', Carbon::now())
                ->select('product_id')
                ->get()->pluck('product_id')->toArray();
            foreach ($ids as $id) {
                $productData[$id] = false;
                $priceData[$id] = false;
                $qtyData[$id] = false;
            }
            $productIds = array_diff($productIds, $ids);
        }
        if (!$isSeller && $productIds) {
            // 精细化表设置产品组与buyer组不可见
            $ids = DelicacyManagementGroup::query()->alias('dmg')
                ->leftJoinRelations(['BuyerGroupLink as bgl', 'ProductGroupLink as pgl'])
                ->whereIn('pgl.product_id', $productIds)
                ->where([
                    'dmg.status' => 1,
                    'pgl.status' => 1,
                    'bgl.status' => 1,
                    'bgl.buyer_id' => $customerId,
                ])
                ->groupBy(['pgl.product_id'])
                ->select('pgl.product_id')
                ->get()->pluck('product_id')->toArray();
            foreach ($ids as $id) {
                $productData[$id] = false;
                $priceData[$id] = false;
                $qtyData[$id] = false;
            }
            $productIds = array_diff($productIds, $ids);
        }
        if ($productIds) {
            // 是否与 seller 建立关联或者产品是否有设置价格或库存可见
            $productInfos = Product::query()->alias('p')
                ->leftJoinRelations('customerPartnerToProduct as ctp')
                ->leftJoin('oc_buyer_to_seller as bts', function ($join) use ($customerId) {
                    $join->on('bts.seller_id', '=', 'ctp.customer_id')
                        ->where([
                            'bts.buyer_control_status' => 1,
                            'bts.seller_control_status' => 1,
                            'bts.buy_status' => 1,
                            'bts.buyer_id' => $customerId,
                        ]);
                })
                ->whereIn('p.product_id', $productIds)
                ->groupBy('p.product_id')
                ->select(['p.price_display', 'p.quantity_display', 'p.status', 'bts.id as associate', 'p.product_id'])
                ->get()
                ->keyBy('product_id')->toArray();
            foreach ($productInfos as $id => $info) {
                $productData[$id] = true; // 默认产品可见
                $priceData[$id] = false;
                $qtyData[$id] = false;
                if ($info['associate']) {
                    // 有关联则均可见
                    $priceData[$id] = true;
                    $qtyData[$id] = true;
                    continue;
                }
                if ($info['status'] != ProductStatus::ON_SALE) {
                    // 未上架产品不可见
                    $productData[$id] = false;
                    continue;
                }
                if ($info['price_display']) {
                    // 产品价格可见
                    $priceData[$id] = true;
                }
                if ($info['quantity_display']) {
                    // 产品库存可见
                    $qtyData[$id] = true;
                }
            }
        }

        $ret = ['product' => $productData, 'price' => $priceData, 'qty' => $qtyData];
        $this->setRequestCachedData($cacheKey, $ret);
        return $ret;
    }
}
