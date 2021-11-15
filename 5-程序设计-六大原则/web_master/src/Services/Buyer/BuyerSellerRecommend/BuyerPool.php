<?php

namespace App\Services\Buyer\BuyerSellerRecommend;

use App\Logging\Logger;
use Illuminate\Support\Collection;

class BuyerPool
{
    private $data;
    private $debugProcess;

    public function __construct(array $buyerIds, bool $debugProcess = false)
    {
        foreach ($buyerIds as $buyerId) {
            $this->data[$buyerId] = [
                'count' => 0, // 已推荐次数
                'score' => 0, // 和 seller 的匹配度
            ];
        }
        $this->debugProcess = $debugProcess;
    }

    /**
     * 移除已推荐次数大于 count 的 buyer
     * @param int $count
     */
    public function removeCountOverThan(int $count)
    {
        $this->data = array_filter($this->data, function ($item) use ($count) {
            return $item['count'] < $count;
        });
    }

    /**
     * 增加已推荐次数
     * @param array $recommendBuyerIds
     */
    public function addRecommendCount(array $recommendBuyerIds)
    {
        foreach ($recommendBuyerIds as $buyerId) {
            $this->data[$buyerId]['count']++;
        }
    }

    /**
     * 根据 id 移除 buyer
     * @param array $ids
     */
    public function removeByIds(array $ids)
    {
        if (!$ids) {
            return;
        }
        $this->data = array_filter($this->data, function ($buyerId) use ($ids) {
            return !in_array($buyerId, $ids);
        }, ARRAY_FILTER_USE_KEY);
    }

    /**
     * 计算所有 buyer 与某个 seller 的匹配度
     * 会重置所有匹配度
     * @param int $sellerId
     * @param MatchScoreComponent $matchScoreComponent
     */
    public function calculateScoresForAll(int $sellerId, MatchScoreComponent $matchScoreComponent)
    {
        $this->resetScore();
        foreach ($this->data as $buyerId => $item) {
            $this->data[$buyerId]['score'] = $matchScoreComponent->getBuyerSellerMatchScore($buyerId, $sellerId);
        }
        if ($this->debugProcess) {
            Logger::buyerSellerRecommend(['type' => '匹配度', 'sellerId' => $sellerId, 'score' => array_map(function ($item) {
                return $item['score'];
            }, $this->data)]);
        }
    }

    /**
     * 重置所有的匹配度
     */
    private function resetScore()
    {
        $this->data = array_map(function ($item) {
            $item['score'] = 0;
            return $item;
        }, $this->data);
    }

    /**
     * 根据优先匹配度获取指定数量的 buyerId
     * @param int $needCount 指定数量
     * @param int $priorityScore 优先匹配度
     * @return array
     */
    public function getIdsWithPriorityScore(int $needCount, int $priorityScore)
    {
        // 拆分优先级
        $zeroScoreData = Collection::make(); // 匹配度为 0 的
        $lowScoreData = Collection::make(); // 小于 $priorityScore 匹配度的，但大于 0 的
        $highScoreData = Collection::make(); // 大于等于 $priorityScore 匹配度的
        foreach ($this->data as $buyerId => $item) {
            if ($item['score'] == 0) {
                $zeroScoreData->push($buyerId);
                continue;
            }
            if ($item['score'] < $priorityScore) {
                $lowScoreData->push($buyerId);
                continue;
            }
            $highScoreData->push($buyerId);
        }
        // 优先级算法
        $result = Collection::make(); // 最终结果
        foreach ([$highScoreData, $lowScoreData, $zeroScoreData] as $scoreData) {
            /** @var Collection $scoreData */
            if ($scoreData->count() <= 0) {
                // 该分组无数据时跳过
                continue;
            }
            if ($scoreData->count() == $needCount) {
                // 该分组的量 = 需求量时：表示获取到足够数据
                $result = $result->merge($scoreData->toArray());
                break;
            }
            if ($scoreData->count() > $needCount) {
                // 该分组的量 > 需求的量时：随机取其中的需求量，足量结束
                $result = $result->merge($scoreData->random($needCount)->toArray());
                break;
            }
            // 该分组的量 < 需求量时：该分组的所有数据并入结果，并减少需求量
            $result = $result->merge($scoreData->toArray());
            $needCount -= $scoreData->count();
        }

        return $result->toArray();
    }

    /**
     * 获取 buyer 匹配度
     * @param int $id BuyerId
     * @return int
     */
    public function getScoreById($id): int
    {
        return $this->data[$id]['score'];
    }

    /**
     * 获取当前 buyer 池的总量
     * @return int
     */
    public function count()
    {
        return count($this->data);
    }

    /**
     * 获取当前所有 buyer id
     * @return array
     */
    public function getBuyerIds()
    {
        return array_keys($this->data);
    }
}
