<?php

namespace App\Enums\Customer;

use App\Models\Customer\CustomerScoreDimension as CustomerScoreDimensionModel;
use Framework\Enum\BaseEnum;

class CustomerScoreDimension extends BaseEnum
{
    // tb_customer_score_dimension 表的数据

    const SELLER_PRODUCT = 1; // 产品，seller评分总维度：产品
    const SELLER_TRADE = 2; // 交易，seller评分总维度：交易
    const SELLER_COMMUNICATION = 3; // 沟通，seller评分总维度：沟通
    const SELLER_REPUTATION = 4; // 信誉，seller评分总维度：信誉
    const BUYER_ACTIVE = 5; // 活跃及激活，buyer评分总维度：活跃及激活
    const BUYER_SALES = 6; // 销售能力，buyer评分总维度：销售能力
    const BUYER_REPUTATION = 7; // 信誉，buyer评分总维度：信誉
    const SELLER_PRODUCT_SOLD_OUT_RATE = 8; // 售罄率，seller评分子级维度：产品-售罄率
    const SELLER_PRODUCT_RMA_RATE = 9; // 退返品率，seller评分子级维度：产品-退返品率
    const SELLER_TRADE_ACTIVE = 10; // 平台活跃度，seller评分总维度：交易-平台活跃度
    const SELLER_TRADE_HISTORY = 11; // 交易时长及历史得分，seller评分总维度：交易-交易时长及历史得分
    const SELLER_TRADE_COMPLEX = 12; // 复杂参与度，seller评分总维度：交易-复杂参与度
    const SELLER_COMMUNICATION_SERVICE_RESPONSE = 13; // 在线客服响应速度，seller评分总维度：沟通-在线客服响应速度
    const SELLER_COMMUNICATION_RMA_RESPONSE = 14; // 退返品响应速度，seller评分总维度：沟通-退返品响应速度
    const SELLER_REPUTATION_RMA_APPROVE_RATE = 15; // 退返品同意率，seller评分总维度：信誉-退返品同意率
    const SELLER_REPUTATION_COMPLEX_COMPLETE_RATE = 16; // 复杂交易履约率，seller评分总维度：信誉-复杂交易履约率
    const SELLER_REPUTATION_DISPUTE = 17; // 违反政策及引起纠纷，seller评分总维度：信誉-违反政策及引起纠纷
    const BUYER_ACTIVE_HISTORY = 18; // 交易时长及历史得分，buyer评分总维度：活跃及激活-交易时长及历史得分
    const BUYER_ACTIVE_ONLINE = 19; // 平台活跃度，buyer评分总维度：活跃及激活-平台活跃度
    const BUYER_SALES_SHIPMENTS = 20; // 月出货量，buyer评分总维度：销售能力-月出货量
    const BUYER_REPUTATION_RMA_RATE = 21; // 销售单退返品率，buyer评分总维度：信誉-销售单退返品率
    const BUYER_REPUTATION_COMPLEX_COMPLETE = 22; // 复杂交易履约率，buyer评分总维度：信誉-复杂交易履约率
    const BUYER_REPUTATION_DISPUTE_RATE = 23; // 纠纷率，buyer评分总维度：信誉-纠纷率
    const BUYER_REPUTATION_VIOLATION = 24; // 违反政策及引起纠纷，buyer评分总维度：信誉-违反政策及引起纠纷

    public static function getViewItems()
    {
        return [
            static::SELLER_PRODUCT => __('产品质量', [], 'enums/customer'),
            static::SELLER_TRADE => __('交易稳定', [], 'enums/customer'),
            static::SELLER_COMMUNICATION => __('服务质量', [], 'enums/customer'),
            static::SELLER_REPUTATION => __('商家信誉', [], 'enums/customer'),
            // 其他继续补充
        ];
    }

    /**
     * seller 评分维度
     * @return array
     */
    public static function sellerDimension()
    {
        return [
            self::SELLER_PRODUCT,
            self::SELLER_TRADE,
            self::SELLER_COMMUNICATION,
            self::SELLER_REPUTATION,
        ];
    }

    /**
     * buyer 评分维度
     * @return array
     */
    public static function BuyerDimension()
    {
        return [
            self::BUYER_ACTIVE,
            self::BUYER_SALES,
            self::BUYER_REPUTATION,
        ];
    }

    /**
     * 获取父级
     * @param int $dimensionId
     * @return int
     */
    public static function getParent(int $dimensionId)
    {
        return cache()->getOrSet([__CLASS__, __FUNCTION__, $dimensionId, 'v1'], function () use ($dimensionId) {
            $model = CustomerScoreDimensionModel::query()->find($dimensionId);
            return $model ? $model->parent_dimension_id : 0;
        }, 60);
    }

    /**
     * 根据得分进行格式化输出
     * @param int $dimensionId
     * @param float $score
     * @return string
     */
    public static function formatScore(int $dimensionId, float $score)
    {
        $moreThanMap = [
            // 需要用到时逐步添加
            // $dimensionId => [大于等于该分值 => 显示内容]
            static::BUYER_ACTIVE_ONLINE => [
                4 => __('高', [], 'common'),
                2 => __('中', [], 'common'),
                0 => __('低', [], 'common'),
            ],
        ];
        if (isset($moreThanMap[$dimensionId])) {
            $map = $moreThanMap[$dimensionId];
            foreach ($map as $max => $text) {
                if ($score >= $max) {
                    return $text;
                }
            }
        }
        return 'N/A';
    }
}
