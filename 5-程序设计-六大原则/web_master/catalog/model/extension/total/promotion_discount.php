<?php

use App\Enums\Marketing\CampaignTransactionType;
use App\Enums\Marketing\CampaignType;
use App\Enums\Marketing\CouponTemplateType;
use App\Enums\Product\ProductTransactionType;
use App\Models\Marketing\Campaign;
use App\Models\Marketing\CampaignCondition;
use App\Models\Marketing\Coupon;
use App\Repositories\Marketing\CampaignRepository;
use App\Services\Marketing\CampaignService;
use Illuminate\Database\Eloquent\Collection;

/**
 * Class ModelExtensionTotalPromotionDiscount
 */
class ModelExtensionTotalPromotionDiscount extends Model
{
    public function __construct($registry)
    {
        parent::__construct($registry);
        $this->load->language('extension/total/promotion_discount');
    }

    /**
     * @param $total
     * @param array $products
     * @param array $params
     */
    public function getTotalByCartId($total, $products = [], $params = [])
    {
        $this->getGiftsAndDiscounts(...func_get_args());
    }

    /**
     * @param $total
     * @param array $products
     * @param array $params
     */
    public function getTotalByProducts($total, $products = [], $params = [])
    {
        $this->getGiftsAndDiscounts(...func_get_args());
    }

    /**
     * @param $total
     */
    public function getTotal($total)
    {
        $total['totals'][] = array(
            'code' => 'giga_coupon',
            'title' => $this->language->get('text_giga_coupon'),
            'value' => 0,
            'sort_order' => $this->config->get('total_giga_coupon_sort_order')
        );
    }

    /**
     * @param $total
     */
    public function getDefaultTotal($total)
    {
        $total['totals'][] = array(
            'code' => 'promotion_discount',
            'title' => $this->language->get('text_promotion_discount'),
            'value' => 0,
            'sort_order' => $this->config->get('total_promotion_discount_sort_order')
        );
    }

    /**
     * @param $total
     * @param array $products
     * @param array $params
     */
    protected function getGiftsAndDiscounts($total, $products = [], $params = [])
    {
        $productIdInfoMap = array_column($products, null, 'product_id');
        $productIds = array_keys($productIdInfoMap);

        // 获取某些产品(包含定金产品)参加的满减或满送活动后，组装为活动为key，一个包含多个产品ids
        $campaignsProductsMap = app(CampaignRepository::class)->getCampaignsProductsMap($productIds);
        $fullReductionCampaigns = []; //满减活动
        $fullDeliveryCampaigns = []; //满送活动

        $campaigns = app(CampaignService::class)->formatPromotionContentForCampaigns($campaignsProductsMap);
        foreach ($campaigns as $campaign) {
            /** @var Campaign $campaign */
            if (empty($campaign->product_ids)) {
                continue;
            }

            // 参加该活动产品总货值
            $joinPromotionAmount = $this->joinPromotionAmount($productIdInfoMap, $campaign);
            if ($joinPromotionAmount == 0) {
                continue;
            }

            // 对比活动条件
            /** @var Collection $conditions */
            $conditions = $campaign->conditions;
            $conditions = $conditions->where('order_amount', '<=', $joinPromotionAmount)->sortByDesc('order_amount')->take(1)->keyBy('mc_id');
            if ($conditions->isEmpty()) {
                continue;
            }

            /** @var CampaignCondition $condition */
            $condition = $conditions->first();
            if ($condition->coupon_template_id && ($condition->couponTemplate->type != CouponTemplateType::BUY_ENOUGH_SEND || !$condition->couponTemplate->is_available)) {
                continue;
            }

            //该用户是否已存在当前模板的优化券
            if ($condition->coupon_template_id && $condition->couponTemplate->per_limit != 0 && !empty(customer()->getId())) {
                $couponCount = Coupon::query()->where('customer_id', customer()->getId())->where('coupon_template_id', $condition->coupon_template_id)->count();
                if ($couponCount >= $condition->couponTemplate->per_limit) {
                    continue;
                }
            }

            $campaign->setRelation('conditions', $conditions);

            if ($campaign->type == CampaignType::FULL_REDUCTION) {
                $fullReductionCampaigns[] = $campaign;
            } elseif ($campaign->type == CampaignType::FULL_DELIVERY) {
                $fullDeliveryCampaigns[] = $campaign;
            }
        }

        // 处理满减活动叠加后活动金额比实际总货值大
        $subTotal = $this->productsSubTotal($productIdInfoMap);

        $minusAmountCampaignIdMap = [];
        $campaignIdCampaignMap = [];
        foreach ($fullReductionCampaigns as $campaign) {
            $campaignIdCampaignMap[$campaign->id] = $campaign;
            $minusAmountCampaignIdMap[$campaign->id] = $campaign->conditions->sum('minus_amount');
        }

        // 活动金额排序 大到小
        arsort($minusAmountCampaignIdMap);

        // 对比活动和总货值
        $sumAmount = 0;
        $newFullReductionCampaigns = [];
        foreach ($minusAmountCampaignIdMap as $campaignId => $amount) {
            $sumAmount += $amount;
            if ($sumAmount > $subTotal) {
                break;
            }
            $newFullReductionCampaigns[] = $campaignIdCampaignMap[$campaignId];
        }

        $total['discounts'] = $newFullReductionCampaigns;
        $total['gifts'] = $fullDeliveryCampaigns;

        // 计算满减总金额
        $fullReductionCampaignAmount = 0;
        foreach ($newFullReductionCampaigns as $campaign) {
            $fullReductionCampaignAmount += $campaign->conditions->sum('minus_amount');
        }

        $total['total'] = $total['total'] - $fullReductionCampaignAmount;
        $total['totals'][] = array(
            'code' => 'promotion_discount',
            'title' => $this->language->get('text_promotion_discount'),
            'value' => -$fullReductionCampaignAmount,
            'sort_order' => $this->config->get('total_promotion_discount_sort_order')
        );
    }

    /**
     * 参加该活动产品总货值
     * @param array $productIdInfoMap
     * @param Campaign $campaign
     * @return float|int
     */
    private function joinPromotionAmount(array $productIdInfoMap, Campaign $campaign)
    {
        $joinPromotionAmount = 0;
        foreach ($productIdInfoMap as $productId => $product) {
            if (!in_array($productId, $campaign->product_ids)) {
                continue;
            }

            // 议价使用normal的活动
            $productType = $product['type_id'] == ProductTransactionType::SPOT ? ProductTransactionType::NORMAL : $product['type_id'];
            if (!in_array(CampaignTransactionType::ALL, $campaign->transaction_types) && !in_array($productType, $campaign->transaction_types)) {
                continue;
            }

            if ($product['type_id'] == ProductTransactionType::SPOT) {
                $joinPromotionAmount += $product['quote_amount'] * $product['quantity'];
            } else {
                $joinPromotionAmount += $product['price'] * $product['quantity'];
            }
        }

        return $joinPromotionAmount;
    }

    /**
     * 当前选中产品的总货值
     * @param array $productIdInfoMap
     * @return float|int
     */
    private function productsSubTotal(array $productIdInfoMap)
    {
        $subTotal = 0;
        foreach ($productIdInfoMap as $productId => $product) {
            if ($product['type_id'] == ProductTransactionType::SPOT) {
                $subTotal += $product['quote_amount'] * $product['quantity'];
            } else {
                $subTotal += $product['price'] * $product['quantity'];
            }
        }

        return $subTotal;
    }
}
