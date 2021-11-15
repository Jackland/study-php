<?php

namespace App\Services\Marketing;

use App\Enums\Common\YesNoEnum;
use App\Enums\Marketing\MarketingTimeLimitConfig;
use App\Enums\Marketing\MarketingTimeLimitProductLogStatus;
use App\Enums\Marketing\MarketingTimeLimitProductStatus;
use App\Enums\Marketing\MarketingTimeLimitStatus;
use App\Enums\Product\ProductAuditStatus;
use App\Enums\Product\ProductAuditType;
use App\Enums\Product\ProductStatus;
use App\Enums\Product\ProductTransactionType;
use App\Enums\Product\ProductType;
use App\Helper\CountryHelper;
use App\Helper\MoneyHelper;
use App\Logging\Logger;
use App\Models\DelicacyManagement\SellerPriceHisotry;
use App\Models\Futures\FuturesMarginAgreement;
use App\Models\Futures\FuturesMarginDelivery;
use App\Models\Margin\MarginAgreement;
use App\Models\Marketing\MarketingTimeLimit;
use App\Models\Marketing\MarketingTimeLimitProduct;
use App\Models\Marketing\MarketingTimeLimitProductLog;
use App\Models\Product\Product;
use App\Models\Product\ProductAudit;
use App\Repositories\Marketing\MarketingDiscountRepository;
use App\Repositories\Marketing\MarketingTimeLimitDiscountRepository;
use Carbon\Carbon;
use Framework\Exception\Exception;
use Illuminate\Database\Capsule\Manager as DB;

class MarketingTimeLimitDiscountService
{
    /**
     * 新增&编辑限时限量活动
     * @param array $postToData
     * @throws \Exception
     * @return bool
     */
    public function storeTimeLimitDiscountInfo(array $postToData): bool
    {
        if (!isset($postToData['products']) || empty($postToData['products'])) {
            throw new \Exception('postToData中未传products');
        }

        $timeZone = CountryHelper::getTimezone(AMERICAN_COUNTRY_ID);
        $saveEffectiveTime = Carbon::parse($postToData['effective_time'])->timezone($timeZone)->toDateTimeString();
        $saveExpirationTime = Carbon::parse($postToData['expiration_time'])->timezone($timeZone)->toDateTimeString();

        $productIds = array_column($postToData['products'], 'product_id');
        $discounts = array_column($postToData['products'], 'discount');

        $productInfos = $product90DaysAveragePrice = [];
        if ($productIds) {
            $productInfos = Product::query()->alias('p')
                ->leftJoinRelations(['customerPartnerToProduct as ctp'])
                ->where('ctp.customer_id', customer()->getId())
                ->whereIn('ctp.product_id', $productIds)
                ->where('p.status', ProductStatus::ON_SALE)
                ->where('p.product_type', ProductType::NORMAL)
                ->where('p.is_deleted', YesNoEnum::NO)
                ->select(['p.product_id', 'p.price', 'p.quantity', 'p.sku'])
                ->get()
                ->keyBy('product_id')
                ->toArray();

            //90天均价校验
            $product90DaysAveragePrice = $this->calculate90DaysAveragePrice($productIds);
        }

        $invalidStatusProducts = $invalidStockQtyProducts = [];
        foreach ($postToData['products'] as $product) {
            // 没查到的都当失效处理
            if (!isset($productInfos[$product['product_id']])) {
                $invalidStatusProducts[$product['product_id']] = $product;
                continue;
            }
            // 库存不足
            $productDetail = $productInfos[$product['product_id']];
            if ($productDetail['quantity'] < $product['origin_qty']) {
                $invalidStockQtyProducts[$product['product_id']] = $product;
            }
        }

        if ($invalidStatusProducts) {
            $skus = implode('、', array_column($invalidStatusProducts, 'sku'));
            Logger::timeLimitDiscount('商品可能已下架:' . $skus);
            throw new \Exception($skus, 801);
        }

        if ($invalidStockQtyProducts) {
            $skus = implode('、', array_column($invalidStockQtyProducts, 'sku'));
            Logger::timeLimitDiscount('商品可能库存不足:' . $skus);
            throw new \Exception($skus, 802);
        }

        if (!isset($postToData['time_limit_id']) || empty($postToData['time_limit_id'])) {
            $timeLimitId = MarketingTimeLimit::query()->insertGetId([
                'seller_id' => customer()->getId(),
                'name' => trim($postToData['name']),
                'transaction_type' => $postToData['transaction_type'],
                'low_qty' => (int)$postToData['low_qty'],
                'store_nav_show' => (int)$postToData['store_nav_show'],
                'pre_hot' => (int)$postToData['pre_hot'],
                'effective_time' => $saveEffectiveTime,
                'expiration_time' => $saveExpirationTime,
                'max_discount' => max($discounts),
            ]);
        } else {
            $timeLimitId = (int)$postToData['time_limit_id'];
            MarketingTimeLimit::query()->where('id', $timeLimitId)->update([
                'name' => trim($postToData['name']),
                'transaction_type' => $postToData['transaction_type'],
                'low_qty' => (int)$postToData['low_qty'],
                'store_nav_show' => (int)$postToData['store_nav_show'],
                'pre_hot' => (int)$postToData['pre_hot'],
                'effective_time' => $saveEffectiveTime,
                'expiration_time' => $saveExpirationTime,
                'max_discount' => max($discounts),
            ]);
            MarketingTimeLimitProduct::query()->where('head_id', $timeLimitId)->delete();
            MarketingTimeLimitProductLog::query()->where('head_id', $timeLimitId)->delete();
        }

        //需要为每个商品记录log，循环写入，最多40条
        $precision = customer()->isJapan() ? 0 : 2 ;

        $finalTimeLimitProduct = [];
        $finalTimeLimitProductLog = [];

        foreach ($postToData['products'] as $product) {
            $productDetail = $productInfos[$product['product_id']];

            $discount = max(100 - (int)$product['discount'], 0);
            if ($discount == 0) {
                throw new \Exception('商品' . $product['product_id'] . '折扣设置错误，折扣为：' . $product['discount']);
            }

            $publicPrice = $product90DaysAveragePrice[$product['product_id']]['average_price'] ?? 0;

            $aferDiscount = MoneyHelper::upperAmount(bcmul($productDetail['price'], $discount / 100), $precision);
            if ($aferDiscount > $publicPrice) {
                throw new \Exception('商品' . $product['product_id'] . "折扣设置错误：当前90天内均价{$publicPrice},公开价打折后价格：" . $aferDiscount . ',折扣：' . $discount);
            }

            $timeLimitProduct = [
                'head_id' => $timeLimitId,
                'product_id' => $product['product_id'],
                'price' => $productDetail['price'],
                'discount' => $discount,
                'origin_qty' => $product['origin_qty'],
                'qty' => $product['origin_qty'],
            ];

            $finalTimeLimitProduct[] = $timeLimitProduct;

            unset($timeLimitProduct['price'], $timeLimitProduct['discount'], $timeLimitProduct['origin_qty']);

            $timeLimitProduct['status'] = MarketingTimeLimitProductLogStatus::ADD;

            $finalTimeLimitProductLog[] = $timeLimitProduct;
        }

        if ($finalTimeLimitProduct) {
            MarketingTimeLimitProduct::query()->insert($finalTimeLimitProduct);
        }
        if ($finalTimeLimitProductLog) {
            MarketingTimeLimitProductLog::query()->insert($finalTimeLimitProductLog);
        }

        return true;
    }

    /**
     * 校验timelimit活动是否 可编辑or停止
     * @param MarketingTimeLimit $marketingTimeLimit
     * @return bool false:不可  true:可
     */
    public function calculateTimeLimitStatus(MarketingTimeLimit $marketingTimeLimit): bool
    {
        $currentTime = Carbon::now()->toDateTimeString();
        if ($marketingTimeLimit->status == MarketingTimeLimitStatus::STOPED || $marketingTimeLimit->effective_time <= $currentTime) {
            return false;
        }
        if ($marketingTimeLimit->pre_hot == 1) {
            //预热期  开始时间往前推24H
            $preHotBeginTime = $marketingTimeLimit->effective_time->subHours(24)->toDateTimeString();
            if ($preHotBeginTime > $currentTime) {
                return true;
            }
            return false;
        }
        return true;
    }

    /**
     * 校验timelimit活动时间段是否重叠 ; 正在进行中和待生效，不能和此2种重叠，压秒也算重叠
     * @param string $startDay
     * @param string $endDay
     * @param int $timeLimitId
     * @return bool
     */
    public function calculateTimeLimitTimePeriodRepeat(string $startDay, string $endDay, int $timeLimitId = 0): bool
    {
        $timeLimitList = MarketingTimeLimit::query()
            ->where('seller_id', customer()->getId())
            ->where('status', '!=', MarketingTimeLimitStatus::STOPED)
            ->where('expiration_time', '>', Carbon::now())
            ->when($timeLimitId > 0, function ($q) use ($timeLimitId) {
                $q->where('id', '!=', $timeLimitId);
            })
            ->select(['effective_time', 'expiration_time'])
            ->get();

        foreach ($timeLimitList as $item) {
            if ($startDay > $item->expiration_time || $endDay < $item->effective_time ) {
                continue;
            }
            return true; // 有重叠
        }

        return false;
    }

    /**
     * 计算 某些商品90天均价 剔除price = 0.00
     * @param array $productIds
     * @return array
     */
    public function calculate90DaysAveragePrice(array $productIds): array
    {
        $decial = customer()->isJapan() ? 0 : 2;
        $defaultZeroPrice = customer()->isJapan() ? '0' : '0.00';
        $beginDate = Carbon::today()->subDays(89)->toDateTimeString();
        $endDate = Carbon::today()->endOfDay()->toDateTimeString();

        // 产品说限制线上上架中的数据必然有审核记录
        $tempProductPrice = $newProductHistoryList = $newFirstAuditRecordList = [];
        $productPriceList = Product::query()
            ->whereIn('product_id', $productIds)
            ->pluck('price', 'product_id')
            ->toArray();
        //符合条件变动历史
        $productHistoryList = SellerPriceHisotry::query()
            ->where('price', '>', 0)
            ->whereIn('product_id', $productIds)
            ->where('add_date', '>=', $beginDate)
            ->where('add_date', '<=', $endDate)
            ->orderBy('add_date')
            ->get()
            ->toArray();
        foreach ($productHistoryList as $item) {
            $newProductHistoryList[$item['product_id']][] = $item;
        }
        //审核记录
        $firstAuditRecordList = ProductAudit::query()
            ->where('is_delete', YesNoEnum::NO)
            ->where('status', ProductAuditStatus::APPROVED)
            ->where('audit_type', ProductAuditType::PRODUCT_INFO)
            ->whereIn('product_id', $productIds)
            ->where('customer_id', customer()->getId())
            ->selectRaw('product_id,min(update_time) as update_time')
            ->groupBy('product_id')
            ->orderBy('id')
            ->get()
            ->toArray();
        foreach ($firstAuditRecordList as $item) {
            $newFirstAuditRecordList[$item['product_id']][] = $item;
        }
        foreach ($productIds as $productId) {
            $existAuditTime = false; // 存在初次审核信息
            $firstCheckDate = null;
            if (isset($newFirstAuditRecordList[$productId])) {
                $firstCheckDate = $newFirstAuditRecordList[$productId][0]['update_time'];
                $existAuditTime = true;
            }
            //符合条件变动历史
            $hasProductHistoryList = $newProductHistoryList[$productId] ?? [];

            // 没有审核记录时候
            if (!$existAuditTime) {
                goto end;
            }

            // 90天外
            if ($firstCheckDate < $beginDate) {
                $beforeRecord = SellerPriceHisotry::query()
                    ->where('price', '>', 0)
                    ->where('product_id', $productId)
                    ->where('add_date', '<', $beginDate)
                    ->orderByDesc('add_date')
                    ->first();
                if ($beforeRecord) {
                    $hasProductHistoryList = array_merge($hasProductHistoryList, [$beforeRecord->toArray()]);
                } else {
                    // 极端情况 拿当前价格
                    if (empty($hasProductHistoryList)) {
                        $hasProductHistoryList[] = [
                            'product_id' => $productId,
                            'price' => $productPriceList[$productId] ?? $defaultZeroPrice,
                        ];
                    }
                }
            } elseif ($firstCheckDate > $beginDate) { // 90天内
                // 重新统计90天均价
                $productHistoryListTwice = SellerPriceHisotry::query()
                    ->where('price', '>', 0)
                    ->where('product_id', $productId)
                    ->where('add_date', '>=', $firstCheckDate)
                    ->where('add_date', '<=', $endDate)
                    ->orderBy('add_date')
                    ->get()
                    ->toArray();
                if ($productHistoryListTwice) {
                    $hasProductHistoryList = $productHistoryListTwice;
                    if ($productHistoryListTwice[0]['add_date'] != $firstCheckDate) {
                        $beforeRecord = SellerPriceHisotry::query()
                            ->where('price', '>', 0)
                            ->where('product_id', $productId)
                            ->where('add_date', '<', $firstCheckDate)
                            ->orderByDesc('add_date')
                            ->first();
                    }
                    if ($beforeRecord) {
                        $hasProductHistoryList = array_merge($productHistoryListTwice, [$beforeRecord->toArray()]);
                    }
                } else {
                    $beforeRecord = SellerPriceHisotry::query()
                        ->where('price', '>', 0)
                        ->where('product_id', $productId)
                        ->where('add_date', '<', $firstCheckDate)
                        ->orderByDesc('add_date')
                        ->first();
                    if ($beforeRecord) {
                        $hasProductHistoryList = array_merge([], [$beforeRecord->toArray()]);
                    } else {
                        $hasProductHistoryList[] = [
                            'product_id' => $productId,
                            'price' => $productPriceList[$productId] ?? $defaultZeroPrice,
                        ];
                    }
                }
            } else {
                if (empty($hasProductHistoryList)) {
                    $hasProductHistoryList[] = [
                        'product_id' => $productId,
                        'price' => $productPriceList[$productId] ?? $defaultZeroPrice,
                    ];
                }
            }

            end:
            // 容错
            if (empty($hasProductHistoryList)) {
                $hasProductHistoryList[] = [
                    'product_id' => $productId,
                    'price' => $productPriceList[$productId] ?? $defaultZeroPrice,
                ];
            }
            $changeTimes = count($hasProductHistoryList);
            $totalPrice = array_sum(array_column($hasProductHistoryList, 'price'));
            if ($changeTimes > 0 && $totalPrice > 0) {
                $averagePrice = MoneyHelper::upperAmount(bcdiv($totalPrice, $changeTimes), $decial);
            } else {
                $averagePrice = $defaultZeroPrice;
            }
            $tempProductPrice[$productId] = [
                'product_id' => $productId,
                'average_price' => $averagePrice,
                'change_time' => $changeTimes,
                'total_price' => $totalPrice
            ];
        }

        return $tempProductPrice;
    }

    /**
     * 补充库存
     * @param int $timeLimitId
     * @param array $products
     * @return bool
     */
    public function incrTimeLimitStockQty(int $timeLimitId, array $products): bool
    {
        $tempLogs = [];
        foreach ($products as $product) {
            MarketingTimeLimitProduct::query()
                ->where('product_id', $product['product_id'])
                ->where('head_id', $timeLimitId)
                ->increment('origin_qty', $product['qty'], ['qty' => DB::raw('qty + ' . $product['qty'])]);
            $tempLogs[] = [
                'head_id' => $timeLimitId,
                'product_id' => $product['product_id'],
                'qty' => $product['qty'],
                'status' => MarketingTimeLimitProductLogStatus::INCR
            ];
        }
        if ($tempLogs) {
            MarketingTimeLimitProductLog::query()->insert($tempLogs);
        }

        return true;
    }

    /**
     * 释放活动商品库存
     * @param int $timeLimitId
     * @param int $productId
     * @return bool
     */
    public function releaseTimeLimitProductStockQty(int $timeLimitId, int $productId): bool
    {
        $timeLimitDetail = MarketingTimeLimit::query()->find($timeLimitId);
        if (empty($timeLimitDetail) || ($timeLimitDetail->effective_status != MarketingTimeLimitStatus::ACTIVE)) {
            return false;
        }
        $discountProductDetail = MarketingTimeLimitProduct::query()->where('head_id', $timeLimitId)->where('product_id', $productId)->first();
        if (empty($discountProductDetail) || $discountProductDetail->status == MarketingTimeLimitProductStatus::RELEASED) {
            return false;
        }

        $res = MarketingTimeLimitProduct::query()->where('id', $discountProductDetail->id)->update(['status' => MarketingTimeLimitProductStatus::RELEASED]);
        if ($res !== false) {
            return true;
        }

        return false;
    }

    /**
     * 计算某个商品目前折扣情况（全店活动和限时活动）
     * @param int $buyerId
     * @param int $sellerId
     * @param int $productId
     * @return array
     */
    public function calculateCurrentDiscountInfo(int $buyerId, int $sellerId, int $productId): array
    {
        $result = [
            'store_discount' => null, // 全店
            'limit_discount' => null, // 限时
            'current_selected' => null, // 初始选中的
            'to_be_active' => null, // 即将生效的限时折扣
        ];

//        if ($buyerId == $sellerId) {
//            return $result;
//        }

        $discountInfo = app(MarketingDiscountRepository::class)->getBuyerDiscountInfo($productId, $sellerId, $buyerId, true);
        $timeLimitDiscount = app(MarketingTimeLimitDiscountRepository::class)->calculateTimeLimitDiscountByProductId($productId);

        $storeWideDiscountInfo  = [];
        if (is_array($discountInfo) & !empty($discountInfo)) {
            $storeWideDiscountInfo = $discountInfo;
        }

        $limitedSalesDiscountInfo = $timeLimitDiscount ? : null;

        $toBeTimeLimitDiscount = app(MarketingTimeLimitDiscountRepository::class)->calculateTimeLimitDiscountByProductId($productId, true);
        $result['to_be_active'] = $toBeTimeLimitDiscount ?: null;

        if ($storeWideDiscountInfo && $limitedSalesDiscountInfo) {
            if ($storeWideDiscountInfo['discount'] < $limitedSalesDiscountInfo['discount']) {
                $result['store_discount'] = $result['current_selected'] = $storeWideDiscountInfo;
                $result['limit_discount'] = $limitedSalesDiscountInfo;
            } else {
                $result['limit_discount'] = $result['current_selected'] = $limitedSalesDiscountInfo;
                $result['store_discount'] = $storeWideDiscountInfo;
            }
        } elseif ($storeWideDiscountInfo) {
            $result['store_discount'] = $result['current_selected'] = $storeWideDiscountInfo;
        } elseif ($limitedSalesDiscountInfo) {
            $result['limit_discount'] = $result['current_selected'] = $limitedSalesDiscountInfo;
        }

        return $result;

    }

    /**
     * 添加活动库存锁定
     * @param $discountObject
     * @param $orderId
     * @param $orderProductId
     * @param $orderProductData
     */
    public function addTimeLimitProductLog($discountObject, $orderId, $orderProductId, $orderProductData)
    {
        if ($discountObject instanceof MarketingTimeLimitProduct) {
            $buyQty = $discountObject->buy_qty ?? 0;
            // 尾款产品不减活动库存
            $productType = Product::query()->where('product_id', $orderProductData['product_id'])->value('product_type');
            if ($productType == ProductType::NORMAL && in_array($orderProductData['type_id'], ProductTransactionType::usedDepositTypes())) {
                return;
            }
            if ($orderProductData['type_id'] == ProductTransactionType::MARGIN) {
                $isBid = MarginAgreement::query()->where('id', $orderProductData['agreement_id'])->value('is_bid');
                //bid不减活动库存
                if ($isBid) {
                    return;
                }
                //转现货不减活动库存
                if (FuturesMarginDelivery::query()->where('margin_agreement_id', $orderProductData['agreement_id'])->exists()) {
                    return;
                }
            }
            if ($orderProductData['type_id'] == ProductTransactionType::FUTURE) {
                $isBid = FuturesMarginAgreement::query()->where('id', $orderProductData['agreement_id'])->value('is_bid');
                //bid不减活动库存
                if ($isBid) {
                    return;
                }
            }

            if (empty($buyQty)) {
                throw new Exception('time limit product qty is empty' . $discountObject->product_id);
            }

            if ($discountObject->qty < $buyQty) {
                throw new Exception('time limit product qty not enough' . $discountObject->product_id);
            }

            MarketingTimeLimitProductLog::query()
                ->insert([
                    'head_id' => $discountObject->id,
                    'product_id' => $discountObject->product_id,
                    'order_product_id' => $orderProductId,
                    'order_id' => $orderId,
                    'transaction_type' => $orderProductData['type_id'],
                    'price' => $orderProductData['price'],
                    'qty' => $buyQty,
                    'status' => MarketingTimeLimitProductLogStatus::LOCKED,
                ]);
        }
    }

    /**
     * 释放活动库存锁定
     * @param int $orderId
     */
    public function unLockTimeLimitProductQty(int $orderId)
    {
        MarketingTimeLimitProductLog::query()
            ->where('order_id', $orderId)
            ->update(['status' => MarketingTimeLimitProductLogStatus::ABANDONED]);
    }

    /**
     * 订单支付完扣减库存
     * @param $orderId
     */
    public function decrementTimeLimitProductQty($orderId)
    {
        $list = MarketingTimeLimitProductLog::query()
            ->where('order_id', $orderId)
            ->where('status', MarketingTimeLimitProductLogStatus::LOCKED)
            ->get(['id', 'head_id', 'qty']);
        foreach ($list as $item) {
            MarketingTimeLimitProduct::query()
                ->where('id', $item->head_id)
                ->decrement('qty', $item->qty);
        }
        MarketingTimeLimitProductLog::query()->whereIn('id', $list->pluck('id')->toArray())->update(['status' => MarketingTimeLimitProductLogStatus::FINISHED]);
    }

    public function generateToken($id)
    {
        return md5($id . MarketingTimeLimitConfig::KEY_MD5);
    }

}
