<?php

namespace App\Repositories\Marketing;

use App\Enums\Common\YesNoEnum;
use App\Enums\Marketing\CampaignRequestProductApprovalStatus;
use App\Enums\Marketing\CampaignRequestStatus;
use App\Enums\Marketing\CampaignType;
use App\Models\Marketing\Campaign;
use App\Models\Marketing\CampaignCondition;
use App\Models\Marketing\CampaignOrder;
use App\Repositories\Rma\RamRepository;

class CampaignRepository
{
    /**
     * 获取某些产品(包含定金产品)参加的满减或满送活动
     * @param array $productIds
     * @return array
     */
    public function getProductsCampaignsMap(array $productIds): array
    {
        // 获取某些产品属于期货定金产品，组装定金产品id和实际产品id的map
        $advanceFutureProductIdsMap = db('oc_futures_margin_process as p')
            ->join('oc_futures_margin_agreement as a', 'p.agreement_id', '=', 'a.id')
            ->whereIn('p.advance_product_id', $productIds)
            ->pluck('a.product_id', 'p.advance_product_id')
            ->toArray();

        // 获取某些产品属于现货定金产品，组装定金产品id和实际产品id的map
        $advanceMarginProductIdsMap = db('tb_sys_margin_process as p')
            ->join('tb_sys_margin_agreement as a', 'p.margin_id', '=', 'a.id')
            ->whereIn('p.advance_product_id', $productIds)
            ->pluck('a.product_id', 'p.advance_product_id')
            ->toArray();

        // 组装获取实际所查活动的产品id
        $realProductIds = array_merge(array_diff($productIds, array_keys($advanceFutureProductIdsMap), array_keys($advanceMarginProductIdsMap)), array_values($advanceFutureProductIdsMap), array_values($advanceMarginProductIdsMap));
        $campaigns = Campaign::query()->alias('c')
            ->join('oc_marketing_campaign_request as cr', function ($join) {
                $join->on('c.id', '=', 'cr.mc_id')
                    ->where('cr.status', CampaignRequestStatus::APPROVED);
            })
            ->join('oc_marketing_campaign_request_product as crp', function ($join) use ($realProductIds) {
                $join->on('cr.id', '=', 'crp.mc_request_id')
                    ->whereIn('crp.product_id', $realProductIds)
                    ->where('crp.status', YesNoEnum::YES)
                    ->where('crp.approval_status', CampaignRequestProductApprovalStatus::APPROVED);
            })
            ->available()
            ->fullTypes()
            ->with(['conditions', 'conditions.couponTemplate'])
            ->get(['c.*', 'crp.product_id']);

        $productIdCampaignsMap = [];
        foreach ($campaigns as $campaign) {
            $productIdCampaignsMap[$campaign->product_id][] = $campaign;
        }

        // 更换定金产品的id, 定金产品不展示满减活动
        $newProductsCampaignsMap = [];
        foreach ($productIds as $productId) {
            $realProductId = $productId;
            if (in_array($productId, array_keys($advanceFutureProductIdsMap))) {
                $realProductId = $advanceFutureProductIdsMap[$productId];
            }
            if (in_array($productId, array_keys($advanceMarginProductIdsMap))) {
                $realProductId = $advanceMarginProductIdsMap[$productId];
            }
            $campaigns = $productIdCampaignsMap[$realProductId] ?? [];
            if (empty($campaigns)) {
                $newProductsCampaignsMap[$productId] = [];
                continue;
            }

            if ($realProductId != $productId) {
                foreach ($campaigns as $k => $campaign) {
                    /** @var Campaign $campaign */
                    if ($campaign->type != CampaignType::FULL_DELIVERY) {
                        unset($campaigns[$k]);
                    }
                }
            }
            $newProductsCampaignsMap[$productId] = $campaigns;
        }

        return $newProductsCampaignsMap;
    }


    /**
     * 获取某些产品(包含定金产品)参加的满减或满送活动后，组装为活动为key，一个包含多个产品ids
     * @param array $productIds
     * @return array
     */
    public function getCampaignsProductsMap(array $productIds): array
    {
        $campaignsProductsMap = [];

        $productsCampaignsMap = $this->getProductsCampaignsMap(...func_get_args());
        foreach ($productsCampaignsMap as $productId => $campaigns) {
            foreach ($campaigns as $campaign) {
                /** @var Campaign $campaign */
                if (isset($campaignsProductsMap[$campaign->id])) {
                    $campaignProductIds = $campaignsProductsMap[$campaign->id]->product_ids;
                    $campaignsProductsMap[$campaign->id]->product_ids = array_merge($campaignProductIds, [$productId]);
                } else {
                    $campaign->setAttribute('product_ids', [$productId]);
                    $campaignsProductsMap[$campaign->id] = $campaign;
                }
            }
        }

        return $campaignsProductsMap;
    }

    /**
     * 获取某个订单参加的满送活动
     * @param int $orderId
     * @return CampaignOrder[]|Illuminate\Database\Eloquent\Collection
     */
    public function getOrderFullDeliveryCampaigns(int $orderId)
    {
        return CampaignOrder::query()->alias('co')
            ->join('oc_marketing_campaign as c', function ($join) {
                $join->on('co.mc_id', '=', 'c.id')
                    ->where('c.type', CampaignType::FULL_DELIVERY);
            })
            ->where('co.order_id', $orderId)
            ->with(['coupon'])
            ->get(['co.*']);
    }

    /**
     * 获取某个商品活动被占用总金额
     *
     * @param int $orderId
     * @param int $orderProductId
     * @param int $productId
     *
     * @return array
     */
    public function calculateCampaignAndCouponTakedAmount($orderId, $orderProductId, $productId)
    {
        $phurseDisaccount = app(RamRepository::class)->getPhurseOrderRmaInfo($orderId, $orderProductId);
        $salesDisaccount = app(RamRepository::class)->getSalesOrderBindInfo($orderId, $productId);

        return [
            'all_taken_campaign_discount' => $phurseDisaccount['all_phurse_campaign_amount'] + $salesDisaccount['all_sales_campaign_amount'],
            'all_taken_coupon_discount' => $phurseDisaccount['all_phurse_coupon_amount'] + $salesDisaccount['all_sales_coupon_amount'],
        ];
    }

    /**
     * 获取满送活动-关联的满送信息
     *
     * @param int $marketingCampId
     * @return CampaignCondition[]
     */
    public function getFullDeliveryCampaign(int $marketingCampId)
    {
        return CampaignCondition::query()->alias('cc')
            ->leftJoin(DB_PREFIX . 'marketing_coupon_template as ct', 'cc.coupon_template_id', '=', 'ct.id')
            ->select('cc.id','cc.remark','cc.coupon_template_id','cc.order_amount','ct.denomination as minus_amount')
            ->where('cc.mc_id', $marketingCampId)
            ->get();
    }
}
