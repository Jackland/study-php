<?php

namespace App\Repositories\Customer;

use App\Enums\Customer\CustomerScoreDimension;
use App\Models\Customer\CustomerScore;
use App\Models\Customer\CustomerScoreSub;

class CustomerScoreDimensionRepository
{
    /**
     * 获取单个维度的得分
     * @param string $scoreTaskNumber
     * @param int $customerId
     * @param int $dimensionId
     * @param false $format 是否进行格式化输出
     * @return float|string
     */
    public function getDimensionScore(string $scoreTaskNumber, int $customerId, int $dimensionId, $format = false)
    {
        $dimensionIds = [];
        $parent = CustomerScoreDimension::getParent($dimensionId);
        if ($parent) {
            $dimensionIds[$parent][] = $dimensionId;
        }
        $scoreArr = $this->getDimensionScores($scoreTaskNumber, $customerId, $dimensionIds);
        $score = $scoreArr[$dimensionId];

        if ($format) {
            return CustomerScoreDimension::formatScore($dimensionId, $score);
        }
        return $score;
    }

    /**
     * 获取多维度的得分
     * @param string $scoreTaskNumber
     * @param int $customerId
     * @param array $dimensionIds [父维度ID => [子维度ID,子维度ID]]
     * @return array [维度ID => 分值，维度ID => 分值]
     */
    public function getDimensionScores(string $scoreTaskNumber, int $customerId, array $dimensionIds)
    {
        $scores = CustomerScore::query()
            ->where('customer_id', $customerId)
            ->whereIn('dimension_id', array_keys($dimensionIds))
            ->get()
            ->keyBy('dimension_id');
        $result = [];
        $subDimensionIds = [];
        foreach ($dimensionIds as $dimensionId => $subIds) {
            if (!isset($scores[$dimensionId])) {
                $result[$dimensionId] = 0;
            } else {
                $result[$dimensionId] = $scores[$dimensionId]->score;
            }
            $subDimensionIds = array_merge($subDimensionIds, $subIds);
        }
        if (!$subDimensionIds) {
            return $result;
        }

        $subScores = CustomerScoreSub::query()->alias('a')
            ->leftJoinRelations(['parentScore as b'])
            ->select(['a.*'])
            ->where('a.customer_id', $customerId)
            ->where('b.task_number', $scoreTaskNumber)
            ->whereIn('a.dimension_id', $subDimensionIds)
            ->get()
            ->keyBy('dimension_id');
        foreach ($subDimensionIds as $dimensionId) {
            if (!isset($subScores[$dimensionId])) {
                $result[$dimensionId] = 0;
            } else {
                $result[$dimensionId] = $subScores[$dimensionId]->score;
            }
        }

        return $result;
    }
}
