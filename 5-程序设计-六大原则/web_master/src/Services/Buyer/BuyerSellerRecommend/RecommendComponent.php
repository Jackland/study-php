<?php

namespace App\Services\Buyer\BuyerSellerRecommend;

use App\Components\BatchInsert;
use App\Logging\Logger;
use App\Models\Buyer\BuyerSellerRecommend;
use App\Models\CustomerPartner\CustomerPartnerToCustomer;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class RecommendComponent
{
    private $sellerOneRecommendCount = 12; // 默认的 seller 一次推荐的数量
    private $oldSellerOneRecommendCount = 4; // 老 seller 一次推荐的数量
    private $oneBuyerMaxCount = 5; // 单个 buyer 最大推荐次数
    private $oldSellerDefaultMaxRecommendCount = 48; // 老 seller 最大推荐次数

    private $sellerPool;
    private $buyerPool;
    private $matchScoreComponent;
    private $debugProcess;

    public function __construct(SellerPool $sellerPool, BuyerPool $buyerPool, bool $debugProcess = false)
    {
        $this->sellerPool = $sellerPool;
        $this->buyerPool = $buyerPool;
        $this->debugProcess = $debugProcess;
        $this->matchScoreComponent = new MatchScoreComponent($this->buyerPool, $this->sellerPool);
    }

    /**
     * 触发推荐
     * @return string
     */
    public function recommend()
    {
        return dbTransaction(function () {
            return $this->recommendInner();
        }, 3);
    }

    /**
     * 推荐算法
     * @return string
     */
    protected function recommendInner()
    {
        $batchInsert = new BatchInsert();
        $batchInsert->begin(BuyerSellerRecommend::class, 500);
        $now = Carbon::now();
        $needUpdateRecommendCountSellerIds = []; // 需要增加已推荐次数的 sellerId
        while (true) {
            // 从所有 seller 中随机取一个（保证 seller 推荐无序）
            $sellerId = $this->sellerPool->randomGetSellerId();
            if (!$sellerId) {
                break;
            }
            // 剔除已经推荐过 5 次的 buyer
            $this->buyerPool->removeCountOverThan($this->oneBuyerMaxCount);
            if ($this->buyerPool->count() <= 0) {
                Logger::buyerSellerRecommend('buyer 数量不足推荐结束', 'warning');
                break;
            }
            // 为该 seller 匹配推荐 buyer
            $buyerPool = clone $this->buyerPool; // 克隆一份用于推荐，防止推荐中修改其中的数据
            $recommendBuyerIdsWithScore = $this->recommendForSeller($sellerId, $buyerPool);
            if ($recommendBuyerIdsWithScore) {
                // 累加 buyer 已推荐次数
                $this->buyerPool->addRecommendCount(array_keys($recommendBuyerIdsWithScore));
                // 保存 buyer seller 推荐数据
                $recommendBuyerIds = Collection::make(array_keys($recommendBuyerIdsWithScore))->shuffle(); // 打乱顺序，确保推荐的无序性
                foreach ($recommendBuyerIds as $buyerId) {
                    $batchInsert->addRow([
                        'seller_id' => $sellerId,
                        'buyer_id' => $buyerId,
                        'match_score' => $recommendBuyerIdsWithScore[$buyerId],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
                // 累加 seller 已推荐次数
                $needUpdateRecommendCountSellerIds[] = $sellerId;
            }
            // 从 seller 池移除该 seller
            $this->sellerPool->removeById($sellerId);
        }
        $batchInsert->end();
        // 增加所有推荐过的 seller 的已推荐次数
        $this->addSellerHasRecommendCount($needUpdateRecommendCountSellerIds);
        // 触发新 seller 转老 seller
        $this->triggerNewSellerToOld($needUpdateRecommendCountSellerIds);
        return $now->format('Y-m-d H:i:s');
    }

    /**
     * 为某个 seller 推荐剩余的 buyer 池中的 buyer
     * @param int $sellerId
     * @param BuyerPool $buyerPool
     * @return array [$buyerId => $score]
     */
    protected function recommendForSeller(int $sellerId, BuyerPool $buyerPool)
    {
        $isOldSeller = $this->sellerPool->isOldSeller($sellerId);
        // 剔除以往推荐中已经给该 seller 推荐过的 buyer
        $hasRecommended = $this->sellerPool->getHasRecommendedBuyerIds($sellerId);
        $buyerPool->removeByIds($hasRecommended);
        // 老 seller 剔除90天内有过交易的
        if ($isOldSeller) {
            $hasTransactions = $this->sellerPool->getHasTransactionsBuyerIds($sellerId, 90);
            $buyerPool->removeByIds($hasTransactions);
        }
        // 计算所有 buyer 与当前 seller 的匹配度
        $buyerPool->calculateScoresForAll($sellerId, $this->matchScoreComponent);
        // 老 seller 一次推荐4个，其他 12 个
        $recommendBuyerCount = $isOldSeller ? $this->oldSellerOneRecommendCount : $this->sellerOneRecommendCount;
        // 筛选匹配度 50 以上的，若不足 N ，则用 50 以下的补足（随机取）
        $needCount = $this->sellerPool->getRealNeedRecommendCountByDate($sellerId, $recommendBuyerCount);
        $ids = $buyerPool->getIdsWithPriorityScore($needCount, 50);
        // 假如需要取12个：在 N 个中随机取 12 个（不足N个时也是随机取 12 个，不足12个时有多少个取多少个）进行推荐
        if (count($ids) > $recommendBuyerCount) {
            $ids = Collection::make($ids)->random($recommendBuyerCount)->toArray();
        }
        if ($this->debugProcess) {
            Logger::buyerSellerRecommend(['type' => '推荐数据', 'sellerId' => $sellerId, 'need' => $needCount, 'get' => count($ids), 'ids' => $ids]);
        }
        // 返回数据 [$buyerId => $score]
        $result = [];
        foreach ($ids as $id) {
            $result[$id] = $buyerPool->getScoreById($id);
        }

        return $result;
    }

    /**
     * 累加已推荐次数
     * @param array $sellerIds
     */
    protected function addSellerHasRecommendCount(array $sellerIds)
    {
        db(CustomerPartnerToCustomer::class)
            ->whereIn('customer_id', $sellerIds)
            ->increment('has_recommend_count', 1);
    }

    /**
     * 触发新 seller 转老 seller，符合条件的转，不符合的不变
     * @param array $sellerIds
     */
    protected function triggerNewSellerToOld(array $sellerIds)
    {
        db(CustomerPartnerToCustomer::class)
            ->whereIn('customer_id', $sellerIds)
            ->where('is_recommended_new', 1) // 新 seller
            ->whereRaw('has_recommend_count >= max_recommend_count') // 已推荐次数已达到最大值
            ->increment('max_recommend_count', $this->oldSellerDefaultMaxRecommendCount, ['is_recommended_new' => 0]);
    }
}
