<?php

namespace App\Repositories\Marketing;

use App\Enums\Common\YesNoEnum;
use App\Enums\Marketing\MarketingDiscountBuyerRangeType;
use App\Enums\Product\ProductTransactionType;
use App\Enums\Product\ProductType;
use App\Helper\CurrencyHelper;
use App\Helper\MoneyHelper;
use App\Logging\Logger;
use App\Models\Buyer\BuyerToSeller;
use App\Models\Customer\Customer;
use App\Models\DelicacyManagement\DelicacyManagement;
use App\Models\Marketing\MarketingDiscount;
use App\Models\Marketing\MarketingDiscountBuyer;
use App\Models\Product\Product;
use Illuminate\Support\Collection;
use App\Models\Link\CustomerPartnerToProduct;
use App\Enums\Marketing\MarketingDiscountStatus;
use Carbon\Carbon;

/**
 * Class MarketingDiscountRepository
 * @package App\Repositories\Marketing
 */
class MarketingDiscountRepository
{

    /**
     * 获取某个seller（或某个折扣）关联的buyer信息
     * @param int $discountId |null
     * @return array [buyer_id => nickname(888)]
     */
    public function getBuyerInfosAssociatedSeller(?int $discountId)
    {
        if (empty($discountId)) {
            return BuyerToSeller::query()
                ->with(['buyerCustomer' => function ($q) {
                    $q->where('status', '=', 1);
                }])
                ->where('seller_id', customer()->getId())
                ->where('buy_status', 1)
                ->where('buyer_control_status', YesNoEnum::YES)
                ->where('seller_control_status', YesNoEnum::YES)
                ->get()
                ->mapWithKeys(function ($item) {
                    if ($item->buyerCustomer) {
                        return [$item->buyer_id => optional($item->buyerCustomer)->nickname . '(' . optional($item->buyerCustomer)->user_number . ')'];
                    }
                    return [];
                })
                ->toArray();
        }
        // 下面暂未用到
        return MarketingDiscountBuyer::query()
            ->with('buyer')
            ->where('discount_id', $discountId)
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->buyer_id => optional($item->buyer)->nickname . '(' . optional($item->buyer)->user_number . ')'];
            })
            ->toArray();
    }

    /**
     * 获取buyer对产品可以使用的折扣
     * @param int $buyerId
     * @param int $productId
     * @return MarketingDiscountBuyer[]|Collection
     */
    public function getProductDiscount($buyerId, $productId)
    {
        $productType = Product::query()->where('product_id', $productId)->value('product_type');
        if ($productType != ProductType::NORMAL) return null;
        $sellerId = CustomerPartnerToProduct::query()->where('product_id', $productId)->value('customer_id');
        return MarketingDiscount::query()
            ->where('product_scope', -1)
            ->where(function ($q) use ($buyerId) {
                $q->where('buyer_scope', -1)->orWhereHas('buyers', function ($query) use ($buyerId) {
                    $query->where('buyer_id', $buyerId);
                });
            })
            ->where('seller_id',$sellerId)
            ->where('effective_time', '<', date('Y-m-d H:i:s'))
            ->where('expiration_time', '>', date('Y-m-d H:i:s'))
            ->where('is_del', YesNoEnum::NO)
            ->orderBy('discount', 'ASC')
            ->get();
    }


    /**
     *获取buyer对产品最大的折扣
     * @param int $buyerId
     * @param int $productId
     * @param null $qty
     * @param null $transactionType
     * @return MarketingDiscountBuyer|\Illuminate\Database\Eloquent\Model|\Illuminate\Database\Eloquent\Relations\HasMany|object|null
     */
    public function getMaxDiscount($buyerId, $productId, $qty = null, $transactionType = null)
    {
        // 精细化价格不走折扣
        if ($transactionType == ProductTransactionType::NORMAL) {
            $delicacyExist = DelicacyManagement::query()->where('buyer_id', $buyerId)
                ->where('product_id', $productId)
                ->where('effective_time', '<=', date('Y-m-d H:i:s', time()))
                ->where('expiration_time', '>', date('Y-m-d H:i:s', time()))
                ->exists();
            if ($delicacyExist) {
                return null;
            }
        }
        // 获取全店折扣
        $discount = $this->getProductDiscount($buyerId, $productId);
        if (!empty($discount)) {
            $discount = $discount->first();
        } else {
            $discount = null;
        }
        // 获取限时限量折扣
        $timeLimit = app(MarketingTimeLimitDiscountRepository::class)->getTimeLimitDiscountByProductId($productId, $transactionType);
        if (empty($discount) && empty($timeLimit)) {
            return null;
        }
        if ($discount && empty($timeLimit)) {
            return $discount;
        }
        $timeLimitDiscount = $timeLimit->products->first();
        if (empty($timeLimitDiscount)) {
            return $discount;
        }
        $lockQty = app(MarketingTimeLimitDiscountRepository::class)->getTimeLimitDiscountLockedQty($timeLimitDiscount->id);
        // 活动剩余数量
        $timeLimitDiscount->qty = $timeLimitDiscount->qty - $lockQty;
        if ($timeLimitDiscount->qty < 0) {
            Logger::marketing('限时限量活动剩余库存异常:' . $timeLimit->name . 'product_id:' . $timeLimitDiscount->product_id);
        }
        if (empty($discount) && $timeLimit) {
            //　不做数量检验
            if ($qty == -1) {
                return $timeLimitDiscount;
            }
            // 判断库存数量足够和最低购买数量
            if ($timeLimitDiscount->qty >= $qty && $timeLimit->low_qty <= $qty) {
                return $timeLimitDiscount;
            } else {
                return null;
            }
        }
        // 当大客户折扣比限时折扣大时
        if ($discount && $timeLimit && $timeLimitDiscount->discount >= $discount->discount) {
            $quantity = Product::query()->where('product_id', $productId)->value('quantity');
            $nonPromotionalQty = $quantity - $timeLimitDiscount->qty;
            if ($nonPromotionalQty < $qty && $timeLimitDiscount->qty >= $qty && $timeLimit->low_qty <= $qty) {
                return $timeLimitDiscount;
            }
        }
        // 当大客户折扣比限时折扣小时
        if ($discount && $timeLimit && $timeLimitDiscount->discount <= $discount->discount) {
            //　不做数量检验
            if ($qty == -1) {
                return $timeLimitDiscount;
            }
            // 判断库存数量足够和最低购买数量
            if ($timeLimitDiscount->qty >= $qty && $timeLimit->low_qty <= $qty) {
                return $timeLimitDiscount;
            }
        }
        return $discount;
    }

    /**
     * 计算产品折扣
     * @param int $buyerId
     * @param int $productId
     * @param float|int $price
     * @param null $qty
     * @param null $transactionType
     * @return float|int
     */
    public function getDiscountPrice($buyerId, $productId, $price, $qty = null, $transactionType = null)
    {
        $discount = $this->getMaxDiscount($buyerId, $productId, $qty, $transactionType);
        if (!$discount) {
            return 0;
        }
        $discount = intval($discount->discount);
        $precision = 2;
        if (CurrencyHelper::getCurrentCode() == 'JPY') {
            $precision = 0;
        }
        return $price - MoneyHelper::upperAmount($price * $discount / 100, $precision);
    }


    /**
     * 计算产品折扣后价格
     * @param int $buyerId
     * @param int $productId
     * @param float|int $price
     * @param null $qty
     * @param null $transactionType
     * @return float|int
     */
    public function getPriceAfterDiscount($buyerId, $productId, $price, $qty = null, $transactionType = null)
    {
        $discount = $this->getMaxDiscount($buyerId, $productId, $qty, $transactionType);
        if (!$discount) {
            return $price;
        }

        $discount = intval($discount->discount);
        $precision = 2;
        if (CurrencyHelper::getCurrentCode() == 'JPY') {
            $precision = 0;
        }
        return MoneyHelper::upperAmount($price * $discount / 100, $precision);
    }

    /**
     * 获取某个buyer对某个商品（seller）的最大折扣
     * @param int|null $productId
     * @param int|null $sellerId
     * @param int $buyerId
     * @param bool $needDiscountInfo
     * @return int|array
     */
    public function getBuyerDiscountInfo(?int $productId, ?int $sellerId, int $buyerId, bool $needDiscountInfo = false)
    {
        //如果传入商品ID，则用商品去定位seller，否则按照传入的seller
        if ($productId) {
            $sellerId = CustomerPartnerToProduct::query()
                ->where('product_id', $productId)
                ->value('customer_id');
        }
        if (!$sellerId || !$buyerId) { // || $sellerId == $buyerId
            return 0;
        }
        //buyer被禁用，也不给看，加强校验下
        $buyerInfo = Customer::find($buyerId);
        if (empty($buyerInfo) || $buyerInfo->status == 0) {
            return 0;
        }
        $currentTime = Carbon::now();
        if ($buyerId != $sellerId) {
            // 当前未关联
            $checkAssociated = BuyerToSeller::query()
                ->where('buyer_id', $buyerId)
                ->where('seller_id', $sellerId)
                ->where('buyer_control_status', YesNoEnum::YES)
                ->where('seller_control_status', YesNoEnum::YES)
                ->exists();
            if (!$checkAssociated) {
                return 0;
            }
            $discountInfo = MarketingDiscount::query()
                ->where('is_del', YesNoEnum::NO)
                ->where('seller_id', $sellerId)
                ->where('effective_time', '<', $currentTime)
                ->where('expiration_time', '>', $currentTime)
                ->where(function ($query) use ($buyerId) {
                    $query->where(function ($query) {
                        $query->where('buyer_scope', '=', MarketingDiscountBuyerRangeType::SCOPE_ALL);
                    })->orWhere(function ($q) use ($buyerId) {
                        $q->whereHas('buyers', function ($q2) use ($buyerId) {
                            $q2->where('buyer_id', $buyerId);
                        });
                    });
                })
                ->orderBy('discount')
                ->first();
        } else {
            // seller自己看商品
            $discountInfo = MarketingDiscount::query()
                ->where('is_del', YesNoEnum::NO)
                ->where('seller_id', $sellerId)
                ->where('effective_time', '<', $currentTime)
                ->where('expiration_time', '>', $currentTime)
                ->orderBy('discount')
                ->first();
        }

        if ($needDiscountInfo && !is_null($discountInfo)) {
            return [
                'discount_id' => $discountInfo->id,
                'discount_type' => 1,
                'discount' => (int)(optional($discountInfo)->discount),
                'discount_name' => $discountInfo->name,
                'transaction_type' => -1,
                'min_buy_num' => 0,
                'last_discount_qty' => 1000000, // 占位
                'last_seconds' => max(Carbon::now()->diffInSeconds($discountInfo->expiration_time, false), 0),
            ];
        }

        return (int)(optional($discountInfo)->discount);
    }

    /**
     * 获取折扣当前状态
     * @param MarketingDiscount $discount
     * @return int
     */
    public function getDiscountEffectiveStatus(MarketingDiscount $discount)
    {
        $currentTime = Carbon::now();
        if ($discount->effective_time > $currentTime) {
            return MarketingDiscountStatus::PENDING;
        } elseif ($discount->effective_time <= $currentTime && $discount->expiration_time >= $currentTime) {
            return MarketingDiscountStatus::ACTIVE;
        } else {
            return MarketingDiscountStatus::INVALID;
        }
    }


}
