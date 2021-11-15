<?php

namespace App\Repositories\Marketing;

use App\Enums\Common\YesNoEnum;
use App\Enums\Marketing\MarketingTimeLimitProductLogStatus;
use App\Enums\Marketing\MarketingTimeLimitProductStatus;
use App\Enums\Marketing\MarketingTimeLimitStatus;
use App\Enums\Product\ProductTransactionType;
use App\Enums\Product\ProductType;
use App\Helper\CurrencyHelper;
use App\Helper\MoneyHelper;
use App\Models\Marketing\MarketingTimeLimit;
use App\Models\Marketing\MarketingTimeLimitProduct;
use App\Models\Marketing\MarketingTimeLimitProductLog;
use App\Models\Product\Product;
use App\Repositories\Product\ProductInfo\BaseInfo;
use App\Repositories\Product\ProductInfo\ProductInfoFactory;
use App\Services\Marketing\MarketingTimeLimitDiscountService;
use Carbon\Carbon;

/**
 * Class MarketingDiscountRepository
 * @package App\Repositories\Marketing
 */
class MarketingTimeLimitDiscountRepository
{
    /**
     * 获取限时活动当前状态
     * @param MarketingTimeLimit $discount
     * @return int
     */
    public function getTimeLimitDiscountEffectiveStatus(MarketingTimeLimit $discount)
    {
        $currentTime = Carbon::now()->toDateTimeString();
        if ($discount->status == MarketingTimeLimitStatus::STOPED) {
            return MarketingTimeLimitStatus::STOPED;
        } elseif ($discount->effective_time > $currentTime) {
            return MarketingTimeLimitStatus::PENDING;
        } elseif ($discount->effective_time <= $currentTime && $discount->expiration_time >= $currentTime) {
            return MarketingTimeLimitStatus::ACTIVE;
        }
        return MarketingTimeLimitStatus::EXPIRED;
    }

    /**
     * 检索商品数据(不复用) 摘自店铺装修并简单调整，用keywords 是否有值区分业务
     * @param string $keywords
     * @param int $customerId
     * @return array
     */
    public function getSearchProducts(array $productIds, string $keywords, int $customerId)
    {
        if (empty($productIds) && empty($keywords)) {
            return ['products' => []];
        }
        $builder = Product::query()->alias('p')
            ->leftJoinRelations(['customerPartnerToProduct as ctp'])
            ->when(!empty($keywords), function ($q) {
                $q->where(array_merge(BaseInfo::AVAILABLE_CONDITION, ['product_type' => ProductType::NORMAL]));
            })
            //->where(array_merge(BaseInfo::AVAILABLE_CONDITION, ['product_type' => ProductType::NORMAL]))
            ->where('ctp.customer_id', $customerId);

        if ($productIds) {
            $builder->where(function ($query) use ($productIds) {
                $query->whereIn('p.product_id', $productIds);
            });
        } else {
            $builder->where(function ($query) use ($keywords) {
                $query->where('p.sku', 'like', '%' . $keywords . '%')
                    ->orWhere('p.mpn', 'like', '%' . $keywords . '%');
            });
        }

        $selectedProductIds = $builder->orderByDesc('p.sku')->select('p.product_id')->get()->pluck('product_id')->unique()->toArray();
        // 获取产品的基础信息
        $productInfoFactory = new ProductInfoFactory();
        $baseInfos = $productInfoFactory
            ->withIds($selectedProductIds)->getBaseInfoRepository()
            ->withCustomerId($customerId);
        // 通过 $productIds判断是否是检索的还是编辑页面的product_ids形式  检索要排除qty<=0  详情页需要展示全部
        if ($keywords) {
            $baseInfos = $baseInfos->withQuantityGreaterThan(0)->getInfos(); // 搜索时候
        } else {
            $baseInfos = $baseInfos->withUnavailable()->getInfos();
        }

        $products = $keyProducts = [];
        $calculate90ProductIds = $productIds ?: $selectedProductIds;
        $products90DaysAveragePrice = [];
        if ($calculate90ProductIds) {
            $products90DaysAveragePrice = app(MarketingTimeLimitDiscountService::class)->calculate90DaysAveragePrice($calculate90ProductIds);
        }
        foreach ($baseInfos as $baseInfo) {
            $tempProduct = [
                'id' => $baseInfo->id,
                'image' => $baseInfo->getImage(60, 60),
                'name' => $baseInfo->name,
                'sku' => $baseInfo->sku,
                'mpn' => $baseInfo->mpn,
                'price' => $baseInfo->price,
                'qty' => $baseInfo->quantity,
                'tags' => $baseInfo->getShowTags(),
                'ninety_days_average_price' => isset($products90DaysAveragePrice[$baseInfo->id]) ? $products90DaysAveragePrice[$baseInfo->id]['average_price'] : 0,
                'currency' => session('currency'),
                'freight' => $baseInfo->getFreight(),
                'unavaliable' => (!$baseInfo->is_on_shelf || $baseInfo->is_deleted) ? 1 : 0,
            ];
            $tempProduct['ninety_days_average_price'] = MoneyHelper::formatPrice($tempProduct['ninety_days_average_price']);

            $products[] = $keyProducts[$baseInfo->id] = $tempProduct;
        }

        return ['products' => $products,'key_products' => $keyProducts];
    }

    /**
     * 获取交易方式列表
     * @return array
     */
    public function getTransactionTypeList()
    {
        $translactionList = ProductTransactionType::getMarketingTimeLimitTransactionType();
        return array_map(function ($item) {
            $itemNew['transaction_type_id'] = $item;
            $itemNew['transaction_type_name'] = ProductTransactionType::getDescription($item);

            return $itemNew;
        }, $translactionList);
    }

    /**
     * 获取某个活动下面 商品失效和最大可补充库存数
     * @param int $timeLimitId
     * @param int $customerId
     * @return array
     */
    public function calculateTimeLimitDiscountIncrInfo(int $timeLimitId, int $customerId)
    {
        $timeLimitDetail = MarketingTimeLimit::query()->with(['products.productDetail'])->find($timeLimitId);
        $timLimitProductInfos = [];
        foreach ($timeLimitDetail->products as $limitProduct) {
            // 剩余活动库存 = 商品活动库存- 已锁定库存
            // 当前上架库存 - 剩余活动库存
            $qty = max($limitProduct->qty - $limitProduct->lockedQty(), 0);
            $maxIncrQuantity = max($limitProduct->productDetail->quantity - $qty, 0);
            $timLimitProductInfos[$limitProduct->product_id] = [
                'max_stock_qty' => $maxIncrQuantity,
                'sku' => $limitProduct->productDetail->sku,
            ];
        }

        return [
            'max_incr_products' => $timLimitProductInfos
        ];
    }

    /**
     * 根据productId获取即将开始或者正在进行的最近的一个限时限量活动
     * @param $productId
     * @param $transactionType
     * @param bool $started
     * @return MarketingTimeLimit|null
     */
    public function getTimeLimitDiscountByProductId($productId, $transactionType, $started = true)
    {
        if (is_null($transactionType)) {
            return null;
        }
        $build = MarketingTimeLimit::query()
            ->with(['products' => function ($q) use ($productId) {
                $q->where('product_id', $productId);
            }])
            ->where('is_del', YesNoEnum::NO)
            ->where('status', '!=', MarketingTimeLimitStatus::STOPED)
            ->when($transactionType != -1, function ($q) use ($transactionType) {
                $q->where(function ($q) use ($transactionType) {
                    return $q->where('transaction_type', 'like', '%' . $transactionType . '%')->orWhere('transaction_type', -1);
                });
            });
        if ($started) {
            $build->where('effective_time', '<=', Carbon::now());
        }
        return $build->whereHas('products', function ($q) use ($productId) {
            $q->where('product_id', $productId)->where('qty', '>', 0)->where('status', MarketingTimeLimitProductStatus::NO_RELEASE);
        })->where('expiration_time', '>=', Carbon::now())
            ->orderBy('effective_time', 'ASC')
            ->first();
    }

    /**
     * 根据productId获取尚未开始的其他限时限量活动的产品的qty
     * @param $productId
     * @param $excludeId
     * @return MarketingTimeLimit[]|\Illuminate\Database\Eloquent\Collection
     */
    public function getTimeLimitDiscountSumQtyByProductId($productId, $excludeId)
    {
        $list = MarketingTimeLimit::query()
            ->with('products')
            ->where('is_del', YesNoEnum::NO)
            ->where('id', '!=', $excludeId)
            ->where('status', '!=', MarketingTimeLimitStatus::STOPED)
            ->whereHas('products', function ($q) use ($productId) {
                return $q->where('product_id', $productId)->where('qty', '>', 0)->where('status', MarketingTimeLimitProductStatus::NO_RELEASE);
            })->where('expiration_time', '>=', Carbon::now())
            ->get();
        $num = 0;
        foreach ($list as $item) {
            $timeLimitDiscount = $item->products()->first();
            $num += $timeLimitDiscount->qty;
        }
        return $num;
    }

    /**
     * 根据productId获取正在进行和即将开始的限时限量活动
     * @param int $productId
     * @param bool $preHot
     * @return array
     */
    public function calculateTimeLimitDiscountByProductId(int $productId, bool $preHot = false)
    {
        $currentTime = Carbon::now();
        $build = MarketingTimeLimit::query()
            ->with('products')
            ->where('is_del', YesNoEnum::NO)
            ->where('status', '!=', MarketingTimeLimitStatus::STOPED);
        if (!$preHot) {
            $build->where('effective_time', '<', $currentTime)
                ->where('expiration_time', '>', $currentTime);
        } else {
            $build->where('pre_hot', YesNoEnum::YES)
                ->where('effective_time', '>', $currentTime)
                ->where('effective_time', '<', (clone $currentTime)->addHours(24)->toDateTimeString()); // 这个反着 用add
        }

        $timeLimitDiscount = $build->whereHas('products', function ($q) use ($productId) {
            return $q->where('product_id', $productId)
                ->where('status', MarketingTimeLimitProductStatus::NO_RELEASE)
                ->where('qty', '>', 0);
        })->orderBy('effective_time')->first(); // 排序要留着

        if (empty($timeLimitDiscount)) {
            return [];
        }
        $limitProducts = $timeLimitDiscount->products->keyBy('product_id')->toArray();
        if (!isset($limitProducts[$productId])) {
            return [];
        }
        // 计算活动剩余库存
        $lastQty = ($limitProducts[$productId]['qty'] ?? 0) - (int)$this->getTimeLimitDiscountLockedQty($limitProducts[$productId]['id']);
        $avaiableQty = Product::query()->where('product_id', $productId)->value('quantity') ?? 0;
        $lastQty = $lastQty < $avaiableQty ? $lastQty : $avaiableQty;

        $result = [
            'discount_id' => $timeLimitDiscount->id,
            'discount_time_token' => app(MarketingTimeLimitDiscountService::class)->generateToken($timeLimitDiscount->id),
            'discount_type' => 2,
            'discount' => $limitProducts[$productId]['discount'],
            'discount_name' => $timeLimitDiscount->name,
            'transaction_type' => $timeLimitDiscount->transaction_type,
            'min_buy_num' => $timeLimitDiscount->low_qty,
            'last_discount_qty' => $lastQty,
            'last_seconds' => max(Carbon::now()->diffInSeconds($timeLimitDiscount->expiration_time, false), 0),
            'buyer_show_discount_name' => $this->getTimeLimitDiscountShowMsg($timeLimitDiscount, 1, $limitProducts[$productId]['discount']),
            'buyer_show_discount_tips' => $this->getTimeLimitDiscountShowMsg($timeLimitDiscount, 2, $limitProducts[$productId]['discount']),
        ];
        if ($preHot) {
            $expireTime = Carbon::now()->diffInSeconds($timeLimitDiscount->effective_time, false);
            $result['last_seconds'] = max($expireTime, 0);
        }

        return $result;
    }

    /**
     * 获取限时限量活动产品锁定的数量
     * @param $timeLimitDiscountId
     * @return int|mixed
     */
    public function getTimeLimitDiscountLockedQty($timeLimitDiscountId)
    {
        $qty = MarketingTimeLimitProductLog::query()
            ->where('head_id', $timeLimitDiscountId)
            ->where('status', MarketingTimeLimitProductLogStatus::LOCKED)
            ->sum('qty');
        return intval($qty);
    }

    /**
     * 获取即将开始或者正在进行的最近的一个限时限量活动信息
     * @param $productId
     * @return \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Eloquent\Relations\HasMany|object|null
     */
    public function getTimeLimitDiscountInfo($productId)
    {
        $timeLimitDiscount = null;
        $timeLimit = $this->getTimeLimitDiscountByProductId($productId, -1);
        if (!empty($timeLimit) && $timeLimitDiscount = $timeLimit->products->first()) {
            $timeLimitDiscount = $timeLimit->products->first();
            // 获取其他的限时限量活动的占用的库存
//            $timeLimitDiscount->other_time_limit_qty = $this->getTimeLimitDiscountSumQtyByProductId($productId, $timeLimit->id);
            // 需求修改-只锁定正在进行的活动库存
            $timeLimitDiscount->other_time_limit_qty = 0;
            $lockQty = $this->getTimeLimitDiscountLockedQty($timeLimitDiscount->id);
            // 活动剩余数量
            $timeLimitDiscount->qty = $timeLimitDiscount->qty - $lockQty;
            $timeLimitDiscount->starting = $timeLimit->effective_time->lte(Carbon::now()) ? true : false;
        }
        return $timeLimitDiscount;
    }

    /**
     * 获取某个正在进行的活动的低库存商品数量
     * @param int $customerId
     * @return int
     */
    public function getEffectiveTimeLimitLowQtyNumber(int $customerId)
    {
        $currentTime = Carbon::now();
        $currentEffectiveDisocunt = MarketingTimeLimit::query()
            ->with(['products'])
            ->where('seller_id', $customerId)
            ->where('is_del', YesNoEnum::NO)
            ->where('status', '<>', MarketingTimeLimitStatus::STOPED)
            ->where('effective_time', '<', $currentTime)
            ->where('expiration_time', '>', $currentTime)
            ->first();
        if (empty($currentEffectiveDisocunt)) {
            return 0;
        }
        $lowQty = $currentEffectiveDisocunt->low_qty;
        return $currentEffectiveDisocunt->products->filter(function ($item) use ($lowQty) {
            return $item->status != MarketingTimeLimitProductStatus::RELEASED && $item->qty < $lowQty;
        })->count();
    }

    /**
     * 处理获取现货折扣信息
     * @param array $discountInfo
     * @param int $transactionType
     * @param int $otherQty
     * @param int $discountQty
     * @return array
     */
    public function handleMarginFutureDiscountInfo(array $discountInfo, int $transactionType, int $otherQty, int $discountQty)
    {
        $storeDiscount = $discountInfo['store_discount'] ?? [];
        $limitDiscount = $discountInfo['limit_discount'] ?? [];

        if ($limitDiscount) {
            $transactionTypeSupport = ($limitDiscount['transaction_type'] == -1 || in_array($transactionType, explode(',', $limitDiscount['transaction_type']))) ? 1 : 0;
            if ($transactionTypeSupport == 0) {
                goto end;
            }
        }

        // 限时活动 交易方式 剩余库存 起购量
        if ($storeDiscount && $limitDiscount) {
            return [
                'store_discount' => [
                    'discount' => $storeDiscount['discount'],
                    'max_qty' => $otherQty, // 其它库存
                ],
                'limit_discount' => [
                    'discount' => $limitDiscount['discount'],
                    'min_buy_num' => $limitDiscount['min_buy_num'],
                    'max_qty' => $discountQty, // 活动库存
                ],
                'discount_type' => 3,
            ];
        }

        // 只有限时折扣
        if ($limitDiscount) {
            return [
                'limit_discount' => [
                    'discount' => $limitDiscount['discount'],
                    'min_buy_num' => $limitDiscount['min_buy_num'],
                    'max_qty' => $discountQty, // 活动库存
                ],
                'discount_type' => 2,
            ];
        }
        end:
        // 只有全店折扣
        if ($storeDiscount) {
            return [
                'store_discount' => [
                    'discount' => $storeDiscount['discount'],
                    'max_qty' => $otherQty, //其它库存
                ],
                'discount_type' => 1,
            ];
        }
        // 没有折扣
        return [
            'discount_type' => 0,
        ];
    }

    /**
     * 获取文案
     * @param MarketingTimeLimit $marketingTimeLimit
     * @param int $type
     * @param int $discount
     * @return string
     */
    public function getTimeLimitDiscountShowMsg(MarketingTimeLimit $marketingTimeLimit, int $type, int $discount)
    {
        if ($type == 1) {
            $transactionTypeMsg = $marketingTimeLimit->transaction_type == -1 ? 'in all transaction types' : "in {$marketingTimeLimit->transaction_type_format} transactions only";
            // 前端90% OFF 这样的字样有span标签，有样式，所以90% OFF 这样的不返回
            return " Applied when Purchase Qty >={$marketingTimeLimit->low_qty} Pcs {$transactionTypeMsg}";
        }

        $transactionTypeMsg = $marketingTimeLimit->transaction_type == -1 ? 'in all transaction types' : "in {$marketingTimeLimit->transaction_type_format} transactions";

        // Rebate transactions, custom price and agreements reached via BID are not applicable to the promotion discounts.
        return "Only when the purchase quantity is more than {$marketingTimeLimit->low_qty} Pcs via direct purchase(Quick View) {$transactionTypeMsg}, the promotion discounts can be applied.";

    }

    /**
     * 参与交易类型的产品价格区间
     * @param array $transactionPriceRange [0=>[单价,单价], 1=>=>[单价,单价], 2=>[单价,单价], 3=>[单价,单价], 4=>[单价,单价]]
     * @param string $transactionType oc_marketing_time_limit.transaction_type
     * @param int $discount 计算公式=price*$discount/100
     * @param string $currencyCode USD
     * @return array
     */
    public function getPriceRangeShow($transactionPriceRange, $transactionType, $discount, $currencyCode)
    {
        $currencies = CurrencyHelper::getCurrencyConfig();
        $precision = $currencies[$currencyCode]['decimal_place'];
        $symbolLeft = $currencies[$currencyCode]['symbol_left'];
        $symbolRight = $currencies[$currencyCode]['symbol_right'];
        $result = [
            'minPrice' => '--',
            'maxPrice' => '--',
            'minPriceShow' => $symbolLeft . '--' . $symbolRight,
            'maxPriceShow' => $symbolLeft . '--' . $symbolRight,
        ];

        $transactionTypeArray = explode(',', $transactionType);
        unset($transactionPriceRange[ProductTransactionType::REBATE]);
        $priceRangeArray = [];
        if (in_array(-1, $transactionTypeArray)) {//不区分交易类型
            if (isset($transactionPriceRange[ProductTransactionType::NORMAL])) {
                $priceRangeArray = array_merge($priceRangeArray, $transactionPriceRange[ProductTransactionType::NORMAL]);
            }
            if (isset($transactionPriceRange[ProductTransactionType::MARGIN])) {
                $priceRangeArray = array_merge($priceRangeArray, $transactionPriceRange[ProductTransactionType::MARGIN]);
            }
            if (isset($transactionPriceRange[ProductTransactionType::FUTURE])) {
                $priceRangeArray = array_merge($priceRangeArray, $transactionPriceRange[ProductTransactionType::FUTURE]);
            }
            if (isset($transactionPriceRange[ProductTransactionType::SPOT])) {
                $priceRangeArray = array_merge($priceRangeArray, $transactionPriceRange[ProductTransactionType::SPOT]);
            }
        } else {
            foreach ($transactionTypeArray as $transactionTypeId) {
                if (isset($transactionPriceRange[$transactionTypeId])) {
                    $priceRangeArray = array_merge($priceRangeArray, $transactionPriceRange[$transactionTypeId]);
                }
            }
        }
        if (!$priceRangeArray) {
            return $result;
        }
        $min = MoneyHelper::upperAmount(min($priceRangeArray) * $discount / 100, $precision);
        $max = MoneyHelper::upperAmount(max($priceRangeArray) * $discount / 100, $precision);

        $result['minPrice'] = $min;
        $result['maxPrice'] = $max;
        $result['minPriceShow'] = CurrencyHelper::formatPrice($min, ['currency' => $currencyCode]);
        $result['maxPriceShow'] = CurrencyHelper::formatPrice($max, ['currency' => $currencyCode]);
        return $result;
    }
}
