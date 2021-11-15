<?php

namespace App\Repositories\Buyer;

use App\Enums\Order\OcOrderStatus;
use App\Enums\Product\ProductType;
use App\Models\Buyer\BuyerToSeller;
use App\Models\CustomerPartner\CustomerPartnerToOrder;
use App\Models\Message\Msg;
use App\Models\Product\Product;
use Carbon\Carbon;
use Exception;
use ModelCatalogSearch;

class BuyerToSellerRepository
{
    /**
     * buyer 和 seller 是否建立联系
     * @param int $sellerId
     * @param int $buyerId
     * @return bool
     */
    public function isConnected(int $sellerId, int $buyerId)
    {
        return BuyerToSeller::query()
            ->where('seller_id', $sellerId)
            ->where('buyer_id', $buyerId)
            ->where('buyer_control_status', 1)
            ->where('seller_control_status', 1)
            ->exists();
    }

    /**
     * 获取buyer to seller model
     *
     * @param int $buyerId
     * @param int $sellerId
     * @return BuyerToSeller|null
     */
    public function getBuyerToSeller(int $buyerId, int $sellerId)
    {
        return BuyerToSeller::query()
            ->where('seller_id', $sellerId)
            ->where('buyer_id', $buyerId)
            ->first();
    }

    /**
     * 获取 buyer 和 seller 最近一次完成的交易的时间
     * @param int $sellerId
     * @param int $buyerId
     * @return Carbon|null
     */
    public function getLastCompleteTransactionOrderDate(int $sellerId, int $buyerId): ?Carbon
    {
        $model = CustomerPartnerToOrder::query()->alias('a')
            ->leftJoinRelations('order as b')
            ->select('b.date_modified')
            ->where('a.customer_id', $sellerId)
            ->where('b.customer_id', $buyerId)
            ->where('b.order_status_id', OcOrderStatus::COMPLETED)
            ->orderByDesc('b.date_modified')
            ->first();
        if (!$model) {
            return null;
        }
        return Carbon::parse($model['date_modified']);
    }

    /**
     * 获取 buyer 和 seller 最近一次消息沟通的时间
     * @param int $sellerId
     * @param int $buyerId
     * @return Carbon|null
     */
    public function getLastConnectMessageDate(int $sellerId, int $buyerId): ?Carbon
    {
        $time = null;

        /** @var Msg $lastSellerToBuyerMsg */
        $lastSellerToBuyerMsg = Msg::queryRead()->alias('s')
            ->joinRelations('receives as r')
            ->where('s.sender_id', $sellerId)
            ->where('r.receiver_id', $buyerId)
            ->orderByDesc('s.id')
            ->first();
        if ($lastSellerToBuyerMsg) {
            $time = Carbon::parse($lastSellerToBuyerMsg->create_time);
        }

        /** @var Msg $lastBuyerToSellerMsg */
        $lastBuyerToSellerMsg = Msg::queryRead()->alias('s')
            ->joinRelations('receives as r')
            ->where('s.sender_id', $buyerId)
            ->where('r.receiver_id', $sellerId)
            ->orderByDesc('s.id')
            ->first();
        if ($lastBuyerToSellerMsg) {
            $lastBuyerToSellerMsgTime = Carbon::parse($lastBuyerToSellerMsg->create_time);
            if (is_null($time)) {
                $time = $lastBuyerToSellerMsgTime;
            } else {
                $time = $time->gt($lastBuyerToSellerMsgTime) ? $time : $lastBuyerToSellerMsgTime;
            }
        }

        return $time;
    }

    /**
     * @param int $customerId
     * @param int $sellerId
     * @return string
     * @throws Exception
     */
    public function getProductCountByCateNew(int $customerId, int $sellerId): string
    {
        $keyCache = implode('_', [__CLASS__, __FUNCTION__, $customerId, $sellerId]);
        $cache = cache();
        if ($cache->has($keyCache)) {
            return $cache->get($keyCache);
        }
        /** @var ModelCatalogSearch $modelCatalogSearch */
        $modelCatalogSearch = load()->model('catalog/search');
        $main_cate_name = 'Others';
        $categories = $modelCatalogSearch->sellerCategories($sellerId);
        $categoriesMap = [];
        $allCategory = collect([]);
        foreach ($categories as $category1) {
            if ($category1['category_id'] == 255 && isset($category1['children'])) {
                foreach ($category1['children'] as $category2) {
                    $categoriesMap[$category2['category_id']]['name'] = $category1['name'] . ' > ' . $category2['name'];
                    $tmp = $modelCatalogSearch->childrenCategories($category2['category_id']);
                    $categoriesMap[$category2['category_id']]['list'] = $tmp;
                    $allCategory = $allCategory->merge($tmp);
                }
            } else {
                $categoriesMap[$category1['category_id']]['name'] = $category1['name'];
                $tmp = $modelCatalogSearch->childrenCategories($category1['category_id']);
                $categoriesMap[$category1['category_id']]['list'] = $tmp;
                $allCategory = $allCategory->merge($tmp);
            }
        }
        if ($categoriesMap) {
            // 获得当前buyer 精细化不可见的产品
            $max = 0;
            $unseenProductIds = $modelCatalogSearch->unSeeProductId($customerId, $sellerId);
            foreach ($categoriesMap as $value) {
                $res = Product::query()->alias('p')
                    ->leftJoinRelations(['customerPartnerToProduct as ctp'])
                    ->leftJoin('oc_product_to_category as otc', 'p.product_id', 'otc.product_id')
                    ->leftJoin('oc_product_to_store as p2s', 'p.product_id', 'p2s.product_id')
                    ->leftJoin('oc_product_description as d', 'p.product_id', 'd.product_id')
                    ->leftJoin('oc_customerpartner_to_customer as ctc', 'ctc.customer_id', 'ctp.customer_id')
                    ->whereIn('otc.category_id', $value['list'])
                    ->when(!empty($unseenProductIds), function ($q) use ($unseenProductIds) {
                        $q->whereNotIn('p.product_id', $unseenProductIds);
                    })
                    ->where([
                        'p.status' => 1,
                        'p.buyer_flag' => 1,
                        'p.is_deleted' => 0,
                        'p2s.store_id' => 0,
                        'd.language_id' => 1,
                        'ctc.show' => 1,
                    ])
                    ->whereIn('product_type', [ProductType::NORMAL])
                    ->where('ctp.customer_id', $sellerId)
                    ->groupBy(['p.product_id'])
                    ->pluck('p.product_id');
                if ($res->count() > $max) {
                    $max = $res->count();
                    $main_cate_name = $value['name'];
                }
            }
        }
        $cache->set($keyCache, $main_cate_name, 3600 * 24);

        return $main_cate_name;
    }

    /**
     * description:获取与buyer相关的seller用户
     * @param int $buyerId
     * @param array $condition
     * @return array
     */
    public function connectedSeller($buyerId, $condition = [])
    {
        return BuyerToSeller::query()
            ->where(['buyer_id' => $buyerId, 'buyer_control_status' => 1, 'seller_control_status' => 1])
            ->whereHas('seller', function ($q) use ($condition) {
                if (isset($condition['screenname']) && $condition['screenname']) {
                    $q->where('screenname', 'like', "%{$condition['screenname']}%");
                }
            })
            ->where(function ($q) use ($condition) {
                if (isset($condition['seller_ids']) && $condition['seller_ids']) {
                    $q->whereIn('seller_id', $condition['seller_ids']);
                }
            })
            ->with(['seller' => function ($q) {
                $q->select(['customer_id', 'screenname']);
            }])
            ->get(['id', 'buyer_id', 'seller_id'])
            ->toArray();
    }

}
