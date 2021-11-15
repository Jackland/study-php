<?php

namespace App\Services\Marketing;

use App\Enums\Marketing\MarketingDiscountBuyerRangeType;
use App\Helper\CountryHelper;
use App\Models\Buyer\BuyerToSeller;
use App\Models\Marketing\MarketingDiscount;
use App\Enums\Marketing\MarketingDiscountProductType;
use App\Models\Marketing\MarketingDiscountLog;
use App\Enums\Marketing\MarketingDiscountLogType;
use App\Models\Marketing\MarketingDiscountBuyer;
use Carbon\Carbon;

class MarketingDiscountService
{
    /**
     * 创建or编辑折扣信息
     * @param int|null $discountId
     * @param string $eventName
     * @param int $discount
     * @param int $buyerScope
     * @param string|null $buyerIds
     * @param string $effectiveTime
     * @param string $expirationTime
     * @return int|null
     * @throws \Exception
     */
    public function storeDiscountInfo(?int $discountId, string $eventName, int $discount, int $buyerScope, ?string $buyerIds, string $effectiveTime, string $expirationTime)
    {
        $timeZone = CountryHelper::getTimezone(AMERICAN_COUNTRY_ID);

        $opType = MarketingDiscountLogType::TYPE_ADD;
        $saveDiscount = max(100 - $discount, 0); // 页面上传10，意思是折扣10，but数据库存90，意思打9折
        $saveEffectiveTime = Carbon::parse($effectiveTime)->timezone($timeZone)->toDateTimeString();
        $saveExpirationTime = Carbon::parse($expirationTime)->timezone($timeZone)->toDateTimeString();

        if (empty($discountId)) {
            //新建折扣
            $discountId = MarketingDiscount::query()->insertGetId([
                'name' => $eventName,
                'seller_id' => customer()->getId(),
                'discount' => $saveDiscount,
                'product_scope' => MarketingDiscountProductType::SCOPE_ALL,
                'buyer_scope' => $buyerScope,
                'effective_time' => $saveEffectiveTime,
                'expiration_time' => $saveExpirationTime,
            ]);
            if ($buyerScope == MarketingDiscountBuyerRangeType::SCOPE_SOME && !empty($buyerIds)) {
                $currentBuyerIds = explode(',', $buyerIds);
                if (is_array($currentBuyerIds)) {
                    $allBuyers = BuyerToSeller::query()->where('seller_id', customer()->getId())->pluck('buyer_id')->toArray();
                    $matchedBuyers = array_intersect($currentBuyerIds, $allBuyers);
                    $insertArr = [];
                    foreach ($matchedBuyers as $matchedBuyer) {
                        $insertArr[] = [
                            'discount_id' => $discountId,
                            'buyer_id' => $matchedBuyer,
                        ];
                    }
                    if ($insertArr) {
                        MarketingDiscountBuyer::query()->insert($insertArr);
                    }
                }
            }
        } else {
            //编辑折扣
            $opType = MarketingDiscountLogType::TYPE_EDIT;
            MarketingDiscount::query()->where('id', $discountId)->update([
                'name' => $eventName,
                'discount' => $saveDiscount,
                'product_scope' => MarketingDiscountProductType::SCOPE_ALL,
                'buyer_scope' => $buyerScope,
                'effective_time' => $saveEffectiveTime,
                'expiration_time' => $saveExpirationTime,
                'update_time' => Carbon::now(),
            ]);
            if ($buyerScope == MarketingDiscountBuyerRangeType::SCOPE_ALL) {
                MarketingDiscountBuyer::query()->where('discount_id', $discountId)->delete();
            } elseif ($buyerScope == MarketingDiscountBuyerRangeType::SCOPE_SOME && !empty($buyerIds)) {
                $currentBuyerIds = explode(',', $buyerIds);
                if (is_array($currentBuyerIds)) {
                    MarketingDiscountBuyer::query()->where('discount_id', $discountId)->delete();
                    $allBuyers = BuyerToSeller::query()->where('seller_id', customer()->getId())->pluck('buyer_id')->toArray();
                    $matchedBuyers = array_intersect($currentBuyerIds, $allBuyers);
                    foreach ($matchedBuyers as $matchedBuyer) {
                        MarketingDiscountBuyer::query()->insert([
                            'discount_id' => $discountId,
                            'buyer_id' => $matchedBuyer,
                        ]);
                    }
                }
            }
        }

        //日志记录
        MarketingDiscountLog::query()->insert([
            'discount_id' => $discountId,
            'type' => $opType,
            'name' => $eventName,
            'discount' => $saveDiscount,
            'product_scope' => MarketingDiscountProductType::SCOPE_ALL,
            'buyer_scope' => ($buyerScope == MarketingDiscountBuyerRangeType::SCOPE_ALL) ? -1 : ($buyerIds ?: ''), //-1代表更新成所有buyer
            'effective_time' => $saveEffectiveTime,
            'expiration_time' => $saveExpirationTime,
        ]);

        return $discountId;
    }

}
