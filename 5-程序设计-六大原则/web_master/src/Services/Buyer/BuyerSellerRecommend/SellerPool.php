<?php

namespace App\Services\Buyer\BuyerSellerRecommend;

use App\Enums\Customer\CustomerAccountingType;
use App\Enums\Order\OcOrderStatus;
use App\Models\Buyer\BuyerSellerRecommend;
use App\Models\CustomerPartner\CustomerPartnerToCustomer;
use App\Models\CustomerPartner\CustomerPartnerToOrder;
use Carbon\Carbon;

class SellerPool
{
    /**
     * 所有 seller 相关数据
     * @var array
     */
    private $data = [];
    /**
     * 将 sellerId 分组
     * 会按照此顺序给出 sellerId
     * 在移除 seller 时会同时移除
     * @see randomGetSellerId
     * @see removeById
     * @var array[]
     */
    private $splitData = [
        'newOuter' => [],
        'newOnsite' => [], //新的onsite排在New Outer后面
        'newInner' => [],
        'oldOuter' => [],
        'oldOnsite' => [], //老的排在Older Outer后面
        'oldInner' => [],
    ];

    public function __construct(array $sellerIds)
    {
        foreach ($sellerIds as $sellerId) {
            $this->data[$sellerId] = [
                'max_recommend_count' => 0, // 最大推荐次数
                'has_recommend_count' => 0, // 已经推荐次数
                'is_new' => false, // 是否是新 seller
                'is_inner' => false, // 是否是内部 seller
                'is_onsite' => false, //是否是onsite seller
            ];
        }
        $this->fillDataInfo();
        $this->splitNewOldData();
    }

    /**
     * 填充推荐次数
     */
    private function fillDataInfo()
    {
        $all = CustomerPartnerToCustomer::query()
            ->with(['customer'])
            ->select(['customer_id', 'has_recommend_count', 'max_recommend_count', 'is_recommended_new'])
            ->whereIn('customer_id', $this->getSellerIds())
            ->get();
        foreach ($all as $item) {
            $this->data[$item->customer_id]['has_recommend_count'] = $item->has_recommend_count;
            $this->data[$item->customer_id]['max_recommend_count'] = $item->max_recommend_count;
            $this->data[$item->customer_id]['is_new'] = (bool)$item->is_recommended_new;
            $this->data[$item->customer_id]['is_inner'] = $item->customer->accounting_type == CustomerAccountingType::INNER;
            $this->data[$item->customer_id]['is_onsite'] = $item->customer->accounting_type == CustomerAccountingType::GIGA_ONSIDE;
        }
    }

    /**
     * 区分新老 seller 到对应的字段
     */
    private function splitNewOldData()
    {
        foreach ($this->data as $sellerId => $item) {
            if ($item['is_new']) {
                // 新 seller
                if ($item['is_inner']) {
                    // 内部
                    $this->splitData['newInner'][$sellerId] = 1;
                } else if ($item['is_onsite']) {
                    // onsite
                    $this->splitData['newOnsite'][$sellerId] = 1;
                } else {
                    // 外部
                    $this->splitData['newOuter'][$sellerId] = 1;
                }
            } else {
                // 老 seller
                if ($item['is_inner']) {
                    // 内部
                    $this->splitData['oldInner'][$sellerId] = 1;
                } elseif ($item['is_onsite']) {
                    // onsite
                    $this->splitData['oldOnsite'][$sellerId] = 1;
                } else {
                    // 外部
                    $this->splitData['oldOuter'][$sellerId] = 1;
                }
            }
        }
    }

    /**
     * 随机取出一个 sellerId
     * @return int|null
     */
    public function randomGetSellerId(): ?int
    {
        foreach ($this->splitData as $item) {
            if (count($item) > 0) {
                return array_rand($item, 1);
            }
        }
        return null;
    }

    /**
     * 根据 id 移除一个 seller
     * @param int $sellerId
     */
    public function removeById(int $sellerId)
    {
        unset($this->data[$sellerId]);
        foreach ($this->splitData as $key => $item) {
            if (isset($item[$sellerId])) {
                unset($this->splitData[$key][$sellerId]);
            }
        }
    }

    /**
     * 是否是老 seller
     * @param int $sellerId
     * @return bool
     */
    public function isOldSeller(int $sellerId): bool
    {
        return !$this->data[$sellerId]['is_new'];
    }

    /**
     * 获取已经推荐次数
     * @param int $sellerId
     * @return int
     */
    public function getHasRecommendCount(int $sellerId): int
    {
        return $this->data[$sellerId]['has_recommend_count'];
    }

    /**
     * 获取所有 sellerId
     * @return array
     */
    public function getSellerIds(): array
    {
        return array_keys($this->data);
    }

    /**
     * 根据给定的日期和需要推荐的数量，计算实际需要提取的推荐数量
     * 因为推荐是有周期的，为了在后续的推荐中保证匹配度的平均，因此实际需要推荐的量为：(总需要推荐次数-已推荐的次数)*每次需要推荐的个数
     * @param int $sellerId
     * @param int $recommendBuyerCount
     * @return int
     */
    public function getRealNeedRecommendCountByDate(int $sellerId, int $recommendBuyerCount): int
    {
        $item = $this->data[$sellerId];
        $batch = $item['max_recommend_count'] - $item['has_recommend_count'];
        if ($batch <= 0) {
            $batch = 1;
        }
        return $batch * $recommendBuyerCount;
    }

    private $_hasRecommended = false;

    /**
     * 获取 seller 已经推荐过的 buyerIds
     * @param int $sellerId
     * @return array
     */
    public function getHasRecommendedBuyerIds(int $sellerId): array
    {
        if ($this->_hasRecommended === false) {
            $models = BuyerSellerRecommend::query()
                ->select(['buyer_id', 'seller_id'])
                ->whereIn('seller_id', $this->getSellerIds())
                ->get();
            $data = [];
            foreach ($models as $model) {
                if (!isset($data[$model->seller_id])) {
                    $data[$model->seller_id] = [];
                }
                $data[$model->seller_id][] = $model->buyer_id;
            }
            $this->_hasRecommended = $data;
        }

        return $this->_hasRecommended[$sellerId] ?? [];
    }

    private $_hasTransactions = false;

    /**
     * 获取与 seller 有过交易的 buyerIds
     * @param int $sellerId
     * @param int $inDays 多少天内
     * @return array
     */
    public function getHasTransactionsBuyerIds(int $sellerId, int $inDays)
    {
        if ($this->_hasTransactions === false) {
            $result = CustomerPartnerToOrder::query()->alias('a')
                ->leftJoinRelations('order as b')
                ->select(['a.customer_id as seller_id', 'b.customer_id as buyer_id'])
                ->whereIn('a.customer_id', $this->getSellerIds())
                ->where('b.order_status_id', OcOrderStatus::COMPLETED) // 完成的采购单
                ->where('b.date_modified', '>', Carbon::now()->subDay($inDays)) // N天内
                ->groupBy(['seller_id', 'buyer_id'])
                ->get()
                ->toArray();
            $data = [];
            foreach ($result as $item) {
                if (!$item['buyer_id']) {
                    continue;
                }
                if (!isset($data[$item['seller_id']])) {
                    $data[$item['seller_id']] = [];
                }
                $data[$item['seller_id']][] = $item['buyer_id'];
            }
            $this->_hasTransactions = $data;
        }

        return $this->_hasTransactions[$sellerId] ?? [];
    }
}
