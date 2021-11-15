<?php

namespace App\Services\Buyer;

use App\Enums\Customer\CustomerAccountingType;
use App\Enums\Order\OcOrderStatus;
use App\Logging\Logger;
use App\Models\Buyer\Buyer;
use App\Models\Buyer\BuyerSellerRecommend;
use App\Models\Buyer\BuyerSellerRecommendNoInterest;
use App\Models\CustomerPartner\CustomerPartnerToCustomer;
use App\Models\Link\CustomerPartnerToProduct;
use App\Models\Order\Order;
use App\Models\Setting\MessageSetting;
use App\Repositories\Customer\CustomerRepository;
use App\Services\Buyer\BuyerSellerRecommend\BuyerPool;
use App\Services\Buyer\BuyerSellerRecommend\RecommendComponent;
use App\Services\Buyer\BuyerSellerRecommend\SellerPool;
use Carbon\Carbon;

class BuyerSellerRecommendService
{
    /**
     * 按国家进行推荐
     * @param int $countryId 国家
     * @return false|string false 表示推荐失败，string 为 Y-m-d H:i:s，可以通过 created_at 获取本次推荐的全部
     */
    public function recommend(int $countryId)
    {
        Logger::buyerSellerRecommend(['推荐开始,' . $countryId]);
        // 获取所有符合条件的 seller
        $outerSellerIds = $this->getRecommendSellerIds($countryId, CustomerAccountingType::OUTER);
        $innerSellerIds = $this->getRecommendSellerIds($countryId, CustomerAccountingType::INNER);
        // 暂时去除对 onsite seller 的推荐（即不取 onsite seller），但是实际推荐的算法中对 onsite seller 的支持没有删除，以防止以后需要恢复对 onsite seller 的推荐
        //$onsiteSellerIds = $this->getRecommendSellerIds($countryId,CustomerAccountingType::GIGA_ONSIDE);
        $onsiteSellerIds = [];

        $sellerIds = array_merge($outerSellerIds, $innerSellerIds, $onsiteSellerIds);
        if (!$sellerIds) {
            Logger::buyerSellerRecommend('无符合条件的 seller 结束', 'warning');
            return false;
        }
        // 获取所有符合条件的 buyer
        $buyerIds = $this->getRecommendBuyerIds($countryId);
        if (!$buyerIds) {
            Logger::buyerSellerRecommend('无符合条件的 buyer 结束', 'warning');
            return false;
        }

        // 推荐
        $debugProcess = defined('BUYER_SELLER_RECOMMEND_PROCESS_DEBUG') ? BUYER_SELLER_RECOMMEND_PROCESS_DEBUG : false;
        Logger::buyerSellerRecommend(['sellerCount' => count($sellerIds), 'buyerCount' => count($buyerIds)]);
        if ($debugProcess) {
            Logger::buyerSellerRecommend(['sellerIds' => $sellerIds]);
            Logger::buyerSellerRecommend(['buyerIds' => $buyerIds]);
        }
        $recommend = new RecommendComponent(new SellerPool($sellerIds), new BuyerPool($buyerIds, $debugProcess), $debugProcess);
        $batchDate = $recommend->recommend();
        Logger::buyerSellerRecommend(["推荐结束,{$countryId},{$batchDate}"]);

        return $batchDate;
    }

    /**
     * seller 访问次数+1
     * @param $recordId
     */
    public function increaseSellerViewCount($recordId)
    {
        db(BuyerSellerRecommend::class)
            ->where('id', $recordId)
            ->increment('seller_view_count', 1);
    }

    /**
     * 标记不感兴趣
     * @param BuyerSellerRecommend $recommend
     * @param string $reason
     */
    public function markNotInterest(BuyerSellerRecommend $recommend, $reason)
    {
        BuyerSellerRecommendNoInterest::create([
            'seller_id' => $recommend->seller_id,
            'buyer_id' => $recommend->buyer_id,
            'recommend_id' => $recommend->id,
            'reason' => $reason,
        ]);
    }

    /**
     * 获取符合推荐条件的 seller
     * @param int $countryId
     * @param int $accountingType
     * @return array
     */
    private function getRecommendSellerIds(int $countryId, int $accountingType)
    {
        // 总推荐次数未达到的
        $subQuery = CustomerPartnerToCustomer::query()->alias('a')
            ->leftJoinRelations('customer as b')
            ->select('a.customer_id')
            ->where('b.status', 1) // 状态正常
            ->where('b.country_id', $countryId) // seller 的市场国别
            ->where('b.accounting_type', $accountingType) // 外部/内部
            ->whereRaw('a.max_recommend_count > a.has_recommend_count') // 总次数 > 已推次数
        ;

        // 外部 seller 需要限定为 中国seller
        if ($accountingType === CustomerAccountingType::OUTER) {
            // 中国 seller
            $chinaSellerQuery = app(CustomerRepository::class)->getChinaSellerIds(true);
            if (!$chinaSellerQuery) {
                Logger::buyerSellerRecommend('无中国 seller 结束', 'warning');
                return [];
            }
            $subQuery->whereIn('a.customer_id', $chinaSellerQuery); // 中国 seller
        }

        // 有上架库存的
        $sellerIds = CustomerPartnerToProduct::query()->alias('a')
            ->leftJoinRelations('product as b')
            ->select('a.customer_id')
            ->whereIn('a.customer_id', $subQuery)
            ->where('b.is_deleted', 0) // 未被删除
            ->where('b.status', 1) // 可用
            ->where('b.buyer_flag', 1) // 可单独售卖的
            ->where('b.quantity', '>', 0) // 有库存的
            ->distinct()
            ->pluck('customer_id')->toArray();

        return $sellerIds;
    }

    /**
     * 获取符合推荐条件的 buyer
     * @param int $countryId
     * @return array
     */
    private function getRecommendBuyerIds(int $countryId)
    {
        // 3个月内有采购订单的
        $subQuery = Order::query()->alias('a')
            ->leftJoinRelations('buyer as b')
            ->select('a.customer_id')
            ->where('b.status', 1) // 正常
            ->where('b.country_id', $countryId) // 国别
            ->where('b.accounting_type', CustomerAccountingType::OUTER) // 外部
            ->where('a.order_status_id', OcOrderStatus::COMPLETED) // 已完成的采购单
            ->where('a.date_modified', '>', Carbon::now()->hour(0)->minute(0)->second(0)->subMonth(3)) // 3个月内
            ->groupBy(['a.customer_id']);
        // 且有电话或选品人微信号，且评分在70分以上的
        $buyerIds = Buyer::query()
            ->select('buyer_id')
            ->whereIn('buyer_id', $subQuery)
            ->where(function ($query) {
                $query->whereNotNull('selector_cellphone')
                    ->orWhereNotNull('selector_wechat')
                    ->orWhereNotNull('selector_qq'); // 有电话或选品人微信号或QQ
            })
            ->validPerformanceScoreOver(60) // 有效评分在60分以上
            ->pluck('buyer_id')->toArray();
        // 剔除未参与推荐的
        $noInBuyerIds = MessageSetting::query()
            ->select('customer_id')
            ->whereIn('customer_id', $buyerIds)
            ->where('is_in_seller_recommend', 0)
            ->pluck('customer_id')->toArray();
        if ($noInBuyerIds) {
            $buyerIds = array_values(array_diff($buyerIds, $noInBuyerIds));
        }

        return $buyerIds;
    }
}
