<?php

namespace App\Services\Marketing;

use App\Enums\Marketing\CampaignTransactionType;
use App\Enums\Marketing\CampaignType;
use App\Enums\Product\ProductTransactionType;
use App\Helper\ArrayHelper;
use App\Models\Marketing\Campaign;
use App\Models\Marketing\CampaignCondition;
use App\Models\Marketing\CampaignOrder;
use Framework\App;
use Illuminate\Database\Eloquent\Collection;

class CampaignService
{
    /**
     * 为促销活动格式化活动内容
     * @param array $campaigns
     * @param null $transactionType 判断产品活动的交易类型是否满足，为null不需判断
     * @return array
     */
    public function formatPromotionContentForCampaigns(array $campaigns, $transactionType = null): array
    {
        $currency = App::session()->get('currency');
        /** @var \Cart\Currency $currencyClass */
        $currencyClass = App::registry()->get('currency');

        // 处理产品促销活动
        $formatCampaigns = [];
        foreach ($campaigns as $campaign) {
            /** @var Campaign $campaign */
            if (!in_array($campaign->type, CampaignType::fullTypes())) {
                continue;
            }
            if (!is_null($transactionType)) {
                // 议价使用normal的活动
                $productType = $transactionType == ProductTransactionType::SPOT ? ProductTransactionType::NORMAL : $transactionType;
                if (!in_array(CampaignTransactionType::ALL, $campaign->transaction_types) && !in_array($productType, $campaign->transaction_types)) {
                    continue;
                }
            }

            if (in_array(CampaignTransactionType::ALL, $campaign->transaction_types)) {
                $promotionContent = '';
            } else {
                $promotionContent = $campaign->transaction_name . ' Transaction: ';
            }
            $promotionTemplate = CampaignType::fullTypesPromotionContentMap()[$campaign->type];
            /** @var Collection $conditions */
            $conditions = $campaign->conditions;
            foreach ($conditions as $k => $condition) {
                /** @var CampaignCondition $condition */
                $orderAmount = $currencyClass->formatCurrencyPrice($condition->order_amount, $currency, '', true, 0);
                if ($campaign->type == CampaignType::FULL_DELIVERY) {
                    if (empty($condition->coupon_template_id)) {
                        $promotion = $condition->remark;
                    } else {
                        $formatCouponDenomination = $currencyClass->formatCurrencyPrice($condition->couponTemplate->denomination, $currency, '', true, 0);
                        $promotion = "a {$formatCouponDenomination} coupon";
                    }
                } else {
                    $promotion = $currencyClass->formatCurrencyPrice($condition->minus_amount, $currency, '', true, 0);
                }
                $promotionContent .= strtr($promotionTemplate, ['{promotion}' => $promotion, '{order_amount}' => $orderAmount]);
                if ($k + 1 < $conditions->count()) {
                    $promotionContent .= ', ';
                }
            }
            //$promotionContent中formatCurrencyPrice直接精度取0：不会有有小数的情况存在整数显示即可
            $campaign->promotion_content = $promotionContent;
            $formatCampaigns[] = $campaign;
        }

        return $formatCampaigns;
    }

    /**
     * 计算一个订单中多个产品参加多个满减活动的每个产品的满减份额
     * @param $campaigns
     * @param $orderProducts
     * @param int $precision
     * @return array
     */
    public function calculateMutiCampaignDiscount($campaigns, $orderProducts, $precision)
    {
        $newProductDiscountMap = [];
        foreach ($campaigns as $campaign) {
            $productDiscountMap = $this->calculatePerCampaignDiscount($campaign->conditions[$campaign->id]->minus_amount, $orderProducts, $campaign->product_ids,$precision);

            foreach ($productDiscountMap as $productId => $discount) {
                if (!isset($newProductDiscountMap[$productId])) {
                    $newProductDiscountMap[$productId]['discount'] = floatval($discount['discount']);
                } else {
                    $newProductDiscountMap[$productId]['discount'] += floatval($discount['discount']);
                }
            }

        }

        return $newProductDiscountMap;
    }

    /**
     * 计算一个满减活动的中每个产品的满减额度
     * @param $minus
     * @param $orderProducts
     * @param $campaignProductIds
     * @param int $precision
     * @return array
     */
    public function calculatePerCampaignDiscount($minus, $orderProducts, $campaignProductIds, $precision = 2)
    {
        $orderProducts = collect($orderProducts)->where('product_type', 0)->keyBy('product_id');
        bcscale(4);
        $data = [];
        $count = count($campaignProductIds);
        $total = 0;
        if ($count == 1) {
            $data[$campaignProductIds[0]]['discount'] = $minus;
            $data[$campaignProductIds[0]]['product_id'] = $campaignProductIds[0];
            return $data;
        }
        // 计算一个活动,参加产品的total
        foreach ($orderProducts as $item) {
            $price = $item['current_price'] ?? $item['price'];
            if ($item['type_id'] == ProductTransactionType::SPOT) {
                $price = $item['quote_amount'] ?? $item['spot_price'];
            }
            if (in_array($item['product_id'], $campaignProductIds)) {
                $total += $price * $item['quantity'];
            }
        }
        foreach ($campaignProductIds as $key => $productId) {
            $price = $orderProducts[$productId]['current_price'] ?? $orderProducts[$productId]['price'];
            if ($orderProducts[$productId]['type_id'] == ProductTransactionType::SPOT) {
                $price = $orderProducts[$productId]['quote_amount'] ?? $orderProducts[$productId]['spot_price'];
            }
            $data[$productId]['product_id'] = $productId;
            if ($key >= $count - 1) {
                $data[$productId]['discount'] = 0;
                break;
            }
            $tmp = bcdiv($price * $orderProducts[$productId]['quantity'], $total);
            $data[$productId]['discount'] = floor(bcmul($tmp, $minus) * pow(10, $precision)) / pow(10, $precision);
        }
        $last = end($data);
        $someTotal = array_sum(array_column($data, 'discount'));
        $data[$last['product_id']]['discount'] = bcsub($minus, $someTotal);
        return $data;
    }

    /**
     * 添加订单的活动记录
     * @param $campaigns
     * @param int $orderId 采购订单ID
     * @return mixed
     */
    public function addCampaignOrder($campaigns, $orderId)
    {
        $data = [];
        foreach ($campaigns as $campaign) {
            $data[] = [
                'order_id' => $orderId,
                'mc_id' => $campaign->id,
                'minus_amount' => $campaign->conditions[$campaign->id]->minus_amount,
                'coupon_template_id' => $campaign->conditions[$campaign->id]->coupon_template_id,
                'remark' => $campaign->conditions[$campaign->id]->remark,
            ];
        }
        return CampaignOrder::insert($data);
    }

}
