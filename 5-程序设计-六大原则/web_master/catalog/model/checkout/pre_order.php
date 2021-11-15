<?php

use App\Enums\Cart\CartAddCartType;
use App\Enums\Common\CountryEnum;
use App\Enums\Order\OcOrderStatus;
use App\Enums\Product\ProductTransactionType;
use App\Enums\Product\ProductType;
use App\Enums\Spot\SpotProductQuoteStatus;
use App\Helper\MoneyHelper;
use App\Models\Futures\FuturesMarginAgreement;
use App\Models\Marketing\Coupon;
use App\Models\Marketing\MarketingDiscountBuyer;
use App\Models\Marketing\MarketingTimeLimitProduct;
use App\Repositories\Margin\MarginRepository;
use App\Repositories\Marketing\CouponRepository;
use App\Repositories\Marketing\MarketingDiscountRepository;
use App\Repositories\Marketing\MarketingTimeLimitDiscountRepository;
use App\Repositories\Product\ProductPriceRepository;
use App\Repositories\Seller\SellerProductRatioRepository;
use Illuminate\Database\Query\Builder;
use kriss\bcmath\BCS;

/**
 * Class ModelCheckoutPreOrder
 * @property ModelCatalogProduct $model_catalog_product
 * @property ModelCheckoutCwfInfo $model_checkout_cwf_info
 * @property ModelFuturesAgreement $model_futures_agreement
 * @property ModelToolImage $model_tool_image
 */
class ModelCheckoutPreOrder extends Model
{
    const PRODUCT_NORMAL = 0; // 常规商品
    const PRODUCT_MARGIN = 1; // 现货头款商品
    const PRODUCT_FUTURE = 2; // 期货头款商品
    const PRODUCT_FREIGHT = 3; // 补运费商品

    const TRANSACTION_NORMAL = 0; //常规
    const TRANSACTION_REBATE = 1; //返点协议
    const TRANSACTION_MARGIN = 2; //现货协议
    const TRANSACTION_FUTURE = 3; //期货协议
    const TRANSACTION_SPOT = 4; //议价协议
    const TRANSACTION_TYPES = ['Normal', 'Rebate', 'Margin', 'Futures', 'Spot Price'];

//    /**
//     * 设置下单页缓存
//     * @param $data
//     */
//    public function setPreOrderCache($data)
//    {
//        if ($data) {
//            $data = json_encode($data);
//            $this->session->set('pre_order', $data);
//        }
//    }

    /**
     * 获取下单页数据
     * @param string $cartIdStr
     * @param string $buyNowData
     * @return array
     * [
     *   ['product_id' => 9533, 'transaction_type' => 0, 'quantity' => 2, 'add_cart_type' => 0,'cart_id' => '', 'agreement_id' => ''],
     * ]
     */
    public function getPreOrderCache($cartIdStr = '', $buyNowData = '')
    {
        if (!empty($buyNowData)) {
            return json_decode(base64_decode($buyNowData), true);
        }

        $data = [];
        if ($cartIdStr) {
            $cartIds = explode(',', $cartIdStr);
            $res = $this->orm->table('oc_cart')->whereIn('cart_id', $cartIds)->get();
            foreach ($res as $key => $item) {
                $data[$key]['product_id'] = $item->product_id;
                $data[$key]['transaction_type'] = $item->type_id;
                $data[$key]['quantity'] = $item->quantity;
                $data[$key]['add_cart_type'] = $item->add_cart_type;
                $data[$key]['cart_id'] = $item->cart_id;
                $data[$key]['agreement_id'] = ($item->type_id == CartAddCartType::DEFAULT_OR_OPTIMAL && $item->agreement_id == 0) ? null : $item->agreement_id;
            }
        }

        return $data;
    }
//
//    /**
//     *  清空下单页缓存
//     * @return mixed
//     */
//    public function removePreOrderCache()
//    {
//        return $this->session->remove('pre_order');
//    }

    /**
     * 获取产品相关数据
     * @param array $productIds
     * @param int $buyerId
     * @return array
     */
    public function getOriginalProducts($productIds, $buyerId)
    {
        $res = $this->orm->table('oc_product as p')
            ->select('p.product_id', 'p.price', 'p.product_type', 'p.subtract', 'p.quantity as stock_quantity', 'p.buyer_flag', 'pd.name', 'p.model', 'p.shipping',
                'p.danger_flag', 'p.image', 'p.combo_flag', 'p.minimum', 'p.weight', 'p.weight_class_id', 'dm.current_price as vip_price',
                'p.length', 'p.width', 'p.height', 'p.sku', 'p.mpn', 'c2c.customer_id  as seller_id', 'cus.customer_group_id',
                'c2c.screenname', 'p.status', 'cus.status as store_status', 'cus.accounting_type')
            ->leftJoin('oc_product_description as pd', 'p.product_id', '=', 'pd.product_id') // 关联获取产品标题
            ->leftJoin('oc_customerpartner_to_product as c2p', 'c2p.product_id', '=', 'p.product_id')  // 关联获取产品所属的seller
            ->leftJoin('oc_customerpartner_to_customer as c2c', 'c2c.customer_id', '=', 'c2p.customer_id')// 关联获取店铺名称
            ->leftJoin('oc_customer as cus', 'c2p.customer_id', '=', 'cus.customer_id')  // 关联获取seller用户账号信息
            ->leftJoin('oc_delicacy_management as dm', function ($query) use ($buyerId) {  // 关联获取精细化价格
                $query->on('dm.product_id', '=', 'p.product_id')
                    ->where('dm.expiration_time', '>=', date('Y-m-d H:i:s', time()))
                    ->where('dm.buyer_id', '=', $buyerId);
            })
            ->where('pd.language_id', (int)$this->config->get('config_language_id'))
            ->whereIn('p.product_id', $productIds)
            ->where('p.date_available', '<=', date('Y-m-d'))
            ->get()->keyBy('product_id');
        return obj2array($res);
    }

    /**
     * 获取某个用户某些产品中存在返点精细化价的产品
     * @param array $productIds
     * @param int $buyerId
     * @return array
     */
    public function getRebateDelicacyManagementProductIds($productIds, $buyerId)
    {
        return $this->orm->table('oc_rebate_agreement as a')
            ->join('oc_rebate_agreement_item as i', 'i.agreement_id', '=', 'a.id')
            ->join('oc_delicacy_management as dm', [
                ['a.buyer_id', '=', 'dm.buyer_id'],
                ['a.seller_id', '=', 'dm.seller_id'],
                ['i.product_id', '=', 'dm.product_id'],
                ['a.effect_time', '=', 'dm.effective_time'],
            ])
            ->where([
                ['a.status', '=', 3],
                ['a.buyer_id', '=', $buyerId],
                ['a.expire_time', '>', date('Y-m-d H:i:s')],
                ['dm.product_display', '=', 1]
            ])
            ->whereIn('dm.product_id', $productIds)
            ->pluck('dm.product_id')
            ->toArray();
    }

    /**
     * 对产品数据进行处理
     * @param $originalProducts
     * @param int $customerId
     * @param $deliveryType
     * @param bool $isCollectionFromDomicile
     * @param int $countryId
     * @return array
     */
    public function handleProductsData($originalProducts, $customerId, $deliveryType, $isCollectionFromDomicile, $countryId)
    {
        $productIds = array_column($originalProducts, 'product_id');
        $productsInfo = $this->getOriginalProducts($productIds, $customerId);
        $rebateDelicacyManagementProductIds = $this->getRebateDelicacyManagementProductIds($productIds, $customerId);
        $products = [];
        foreach ($originalProducts as $item) {
            $item = array_merge($productsInfo[$item['product_id']], $item);
            // 存在返点精细化价的产品且是普通购买的删除vip_price
            if ($item['transaction_type'] == self::TRANSACTION_NORMAL && $item['vip_price'] && in_array($item['product_id'], $rebateDelicacyManagementProductIds)) {
                $item['vip_price'] = null;
            }
            // 计算产品的当前价格
            // 由于议价一期写入oc_order_product.price表中的价格不是议价价格，而是非协议价格,此处要兼容
            list($item['current_price'], $item['spot_price'], $item['discount_price'], $item['product_price_per'], $item['service_fee_per'], $item['normal_price'],$item['discount_info']) = $this->calculateCurrentPrice($customerId, $item, $countryId);
            // 非欧洲、上门取货的buyer在非精细化价格、非议价时 减去运费, 不论何种类型的buyer，下单时均需记录运费
            $freightAndPackageResult = $this->cart->getFreightAndPackageFee($item['product_id'], 1, $deliveryType);
            //获取该产品超重附加费
            $item['freight_per'] = $freightAndPackageResult['overweight_surcharge'] + $freightAndPackageResult['freight'];
            $item['overweight_surcharge'] = $freightAndPackageResult['overweight_surcharge'];
            $item['volume'] = $freightAndPackageResult['volume'];
            $item['volume_inch'] = $freightAndPackageResult['volume_inch'];
            $item['package_fee_per'] = $freightAndPackageResult['package_fee'];
            $item['base_freight'] = $freightAndPackageResult['freight'];
            $item['delivery_type'] = $deliveryType;
            $item['stock_quantity'] = $this->calculateProductStockQty($item['product_id'], $item['stock_quantity'], $item['discount_info']);
            // 计算每个产品total价格
            $item['total'] = $this->calculateProductTotalPrice($item['current_price'], $item['package_fee_per'], $item['quantity'], $item['freight_per'], $isCollectionFromDomicile);
            $item['realTotal'] = round($item['current_price'], 2) * $item['quantity']; // 实际支付总价
            // 查询协议编号
            $item['agreement_code'] = $this->getAgreementCode($item['agreement_id'],$item['transaction_type']);
            $item['type_id'] = $item['transaction_type'];//兼容之前的 $cart->getProduct()
            $item['combo'] = $item['combo_flag'];//兼容之前的 $cart->getProduct()
            $products[] = $item;
        }

        return $products;
    }

    /**
     * 计算当前库存
     * @param $productId
     * @param $availableQty integer 在库库存
     * @param $discountInfo MarketingTimeLimitProduct|MarketingDiscountBuyer 当前使用的折扣
     * @return int|mixed
     */
    public function calculateProductStockQty($productId, $availableQty, $discountInfo)
    {
        // 获取即将开始或者正在进行的限时限量折扣
        $timeLimitDiscount = app(MarketingTimeLimitDiscountRepository::class)->getTimeLimitDiscountInfo($productId);
        if (empty($timeLimitDiscount)) {
            return $availableQty;
        }
        if ($discountInfo instanceof MarketingTimeLimitProduct) {
            $timeLimitDiscount->time_limit_buy = true;
        } else {
            $timeLimitDiscount->time_limit_buy = false;
        }
        $qty = $availableQty - $timeLimitDiscount->qty - $timeLimitDiscount->other_time_limit_qty; // 当存在限时限量折扣时，　其他库存　＝　上架库存　－　最近一个限时限量折扣库存　－　其他未来的限时限量折扣库存
        $qty < 0 && $qty = 0;
        $timeLimitQty = 0;
        if ($timeLimitDiscount->starting) {
            $timeLimitQty = ($timeLimitDiscount->qty　 < $availableQty) ? $timeLimitDiscount->qty : $availableQty; // 活动库存
        }
        if ($timeLimitDiscount->time_limit_buy) {
            $qty = $timeLimitQty;
        }
        return $qty;
    }

    /**
     * 下单页面数据显示处理
     * @param array $products
     * @param bool $isCollectionFromDomicile
     * @param int $countryId
     * @param bool $isEurope
     * @return mixed
     * @throws Exception
     */
    public function preOrderShow($products, $isCollectionFromDomicile,$countryId,$isEurope)
    {
        $currency = $this->session->get('currency');
        foreach ($products as &$item) {
            // 由于议价一期写入oc_order_product.price表中的价格不是议价价格，而是非协议价格,此处页面要显示议价价格
            $price = $item['current_price'];
            if (!empty($item['spot_price'])) {
                $price = $item['spot_price'];
            }
            $total = $this->calculateProductTotalPrice($price, $item['package_fee_per'], $item['quantity'], $item['freight_per'], $isCollectionFromDomicile);
            $item['seller_name'] = $item['screenname'];
            if ($isCollectionFromDomicile) {//上门取货
                $item['freight_per'] = $item['package_fee_per'];
            } else {
                $item['freight_per'] = $item['freight_per'] + $item['package_fee_per'];
            }
            // 议价协议时,计算议价的折扣金额,服务费
            if ($item['transaction_type'] == self::TRANSACTION_SPOT) {
                $item = $this->calculateSpotDiscountAmount($item, $countryId, $isEurope, $currency);
            }
            // 记录一些基础的信息
            $item['price_calc'] = $price;
            $item['service_fee_per_calc'] = $item['service_fee_per'];
            $item['product_price_per_calc'] = $item['product_price_per'];
            $item['freight_per_calc'] = $item['freight_per'];
            $item['price'] = $this->currency->formatCurrencyPrice($price, $currency);
            $item['price_money'] = $price;
            $item['service_fee_per'] = $this->currency->formatCurrencyPrice($item['service_fee_per'], $currency);
            $item['product_price_per'] = $this->currency->formatCurrencyPrice($item['product_price_per'], $currency);
            $item['freight_per'] = $this->currency->formatCurrencyPrice($item['freight_per'], $currency);
            $item['total'] = $this->currency->formatCurrencyPrice($total, $currency);
            $item['total_money'] = $total;
            $item['color_str'] = $this->getProductColor($item['product_id']);
            //处理图片尺寸
            $item['thumb'] = $this->handleProductImage($item['image']);
        }
        return $products;
    }

    /**
     * 获取订单的可用优惠券
     * @param $amountRequirement
     * @param array $defaultCouponIds
     * @param int $fullReductionCampaignAmount
     * @return array
     */
    public function getPreOrderCoupons($amountRequirement, $defaultCouponIds = [], $fullReductionCampaignAmount = 0)
    {
        $currency = $this->session->get('currency');
        $coupons = app(CouponRepository::class)->getCustomerAvailableCouponsByOrderAmount($this->customer->getId(), floatval($amountRequirement));
        $checkedCouponValues = [];
        $checkedCouponIds = [];
        foreach ($coupons as $k => $coupon) {
            if ($amountRequirement - $fullReductionCampaignAmount < $coupon->denomination) {
                unset($coupons[$k]);
                continue;
            }
            /** @var Coupon $coupon */
            $coupon->format_denomination = $this->currency->formatCurrencyPrice($coupon->denomination, $currency, '',  true, $coupon->denomination == floor($coupon->denomination) ? 0 : null);
            $coupon->format_order_amount = $this->currency->formatCurrencyPrice($coupon->order_amount, $currency, '',  true, $coupon->order_amount == floor($coupon->order_amount) ? 0 : null);
            $coupon->checked_coupon = false;
            if (in_array($coupon->id, $defaultCouponIds)) {
                $coupon->checked_coupon = true;
                $checkedCouponValues[] = "{$coupon->format_denomination} OFF Order Over {$coupon->format_order_amount}";
                $checkedCouponIds[] = $coupon->id;
            }
        }

        return [
            'coupons' => $coupons,
            'checked_coupon_values' => join(',', $checkedCouponValues),
            'checked_coupon_ids' => join(',', $checkedCouponIds),
        ];
    }

    /**
     *  计算议价的折扣金额,服务费
     * @param $item
     * @param int $countryId
     * @param bool $isEurope
     * @param string $currency
     * @return array
     */
    public function calculateSpotDiscountAmount($item, $countryId, $isEurope, $currency)
    {
        // 议价协议时,计算议价的折扣金额,服务费
        // 欧洲国家的商品金额要拆分成：产品金额+服务费金额
        $productPricePer = app(SellerProductRatioRepository::class)->calculationSellerDisplayPrice($item['seller_id'], $item['normal_price'], $countryId);//欧洲展示价格
        $serviceFeePer = $item['normal_price'] - $productPricePer;
        $discountAmount = bcsub($item['normal_price'], $item['spot_price'], 2); // 单商品折扣金额 = 原价/精细化价-议价
        // 欧洲国家的折扣金额要拆分成：产品折扣+服务费折扣
        $item['quote_discount_amount_per'] = app(SellerProductRatioRepository::class)->calculationSellerDisplayPrice($item['seller_id'], $discountAmount, $countryId); // 欧洲议价每件商品折扣金额
        $item['quote_discount_service_per'] = bcsub($discountAmount, $item['quote_discount_amount_per'], 2); //  欧洲议价每件商品服务费的折扣金额
        $item['quote_service_fee_per'] = $this->currency->formatCurrencyPrice(round(-$item['quote_discount_service_per'], 2), $currency); //显示欧洲议价每件商品服务费的折扣金额
        $item['service_fee_per'] = round($serviceFeePer - $item['quote_discount_service_per'], 2);
        $item['product_price_per'] = round($item['spot_price'] - $item['service_fee_per'], 2);

        //#31737 议价协议免税buyer当前价格不包含税价
        $item['quote_discount_amount_per'] = BCS::create($item['current_price'], ['scale' => 2])->sub($item['service_fee_per'], $item['product_price_per'], $item['quote_discount_service_per'])->getResult();

        $item['str_quote_amount_per'] = $this->currency->formatCurrencyPrice(round(- $item['quote_discount_amount_per'], 2), $currency); //显示议价单商品的折扣金额
        return $item;
    }

    /**
     * 计算当前显示单价
     * 有精细化价格取精细化价格，没有精细化价格，取常规价/阶梯价进行展示
     * @param int $customerId
     * @param $item
     * @param $countryId
     * @return array
     */
    protected function calculateCurrentPrice($customerId,$item, $countryId): array
    {
        $currentPrice = $item['price'];
        // 如果有(精细化)
        if ($item['vip_price']) {
            $currentPrice = $item['vip_price'];
        }

        $normalPrice = $currentPrice;
        $transactionType = $item['transaction_type'];
        // 议价没有折扣
        if ($item['transaction_type'] == self::TRANSACTION_SPOT && !empty($item['agreement_id'])) {
            $transactionType = null;
        }
        // 获取大客户折扣
        $discountInfo = app(MarketingDiscountRepository::class)->getMaxDiscount($customerId, $item['product_id'], $item['quantity'],$transactionType); //产品折扣
        $discount = $discountInfo->discount ?? null; //产品折扣
        $discountRate = $discount ? intval($discount) / 100 : 1;
        $precision = $countryId == CountryEnum::JAPAN ? 0 : 2;

        // 如果add_cart_type=2,选择了阶梯价格以阶梯价展示,
        // 如果add_cart_type=0，选择最优价
        $useWkProQuotePrice = false;
        $discountSpotInfo = null;
        if ($item['transaction_type'] == self::TRANSACTION_NORMAL && in_array($item['add_cart_type'], [CartAddCartType::DEFAULT_OR_OPTIMAL, CartAddCartType::TIERED])) {
            // 获取大客户折扣
            $discountSpotInfo = app(MarketingDiscountRepository::class)->getMaxDiscount($customerId, $item['product_id'], $item['quantity'], ProductTransactionType::SPOT); //产品折扣
            $discountSpot = $discountSpotInfo->discount ?? null; //产品折扣
            $discountSpotRate = $discountSpot ? intval($discountSpot) / 100 : 1;

            $wkProQuoteDetail = $this->orm->table('oc_wk_pro_quote_details')->where('product_id', $item['product_id'])
                ->where('seller_id', $item['seller_id'])
                ->where('min_quantity', '<=', $item['quantity'])
                ->where('max_quantity', '>=', $item['quantity'])
                ->first();
            if (!empty($wkProQuoteDetail)) {
                // 如果add_cart_type=2,选择了阶梯价格以阶梯价展示
                if ($item['add_cart_type'] == CartAddCartType::TIERED) {
                    $useWkProQuotePrice = true;
                    $currentPrice = $wkProQuoteDetail->home_pick_up_price;
                }
                // 如果add_cart_type=0，选择最优价
                if ($item['add_cart_type'] == 0) {
                    // #31737 需要计算出当前免税加后的折扣价对比 (因精细化不需要做折扣)
                    $vatPrice = app(ProductPriceRepository::class)->getProductActualPriceByBuyer(intval($item['seller_id']), $customerId, $currentPrice);
                    $vatWkProQuotePrice = app(ProductPriceRepository::class)->getProductActualPriceByBuyer(intval($item['seller_id']), $customerId, $wkProQuoteDetail->home_pick_up_price);

                    $vatDiscountPrice = $vatPrice;
                    if (!$item['vip_price']) {
                        $vatDiscountPrice = MoneyHelper::upperAmount($vatPrice * $discountRate, $precision);
                    }
                    $vatWkProQuoteDiscountPrice = MoneyHelper::upperAmount($vatWkProQuotePrice * $discountSpotRate, $precision);
                    if ($vatWkProQuoteDiscountPrice < $vatDiscountPrice) {
                        $useWkProQuotePrice = true;
                        $currentPrice = $wkProQuoteDetail->home_pick_up_price;
                    }
                }
            }
        }

        // 判断不是头款,且是一个协议类型走协议价格
        if (!in_array($item['product_type'], [self::PRODUCT_MARGIN, self::PRODUCT_FUTURE]) && $item['transaction_type']) {
            $agreement = $this->cart->getTransactionTypeInfo($item['transaction_type'], $item['agreement_id'], $item['product_id']);
            // 由于议价一期写入oc_order_product.price表中的价格不是议价价格，而是非协议价格,
            // 为了兼容,transaction_type为议价时,$currentPrice是原价/精细化价格
            // $spotPrice为议价价格
            if ($item['transaction_type'] == self::TRANSACTION_SPOT) {
                $spotPrice = $agreement['price'];
            }else {
                $currentPrice = $agreement['price'];
            }
        }

        // 欧洲国家的商品金额要拆分成：产品金额+服务费金额
        $productPricePer = app(SellerProductRatioRepository::class)->calculationSellerDisplayPrice($item['seller_id'], $currentPrice, $countryId);
        $serviceFeePer = $currentPrice - $productPricePer;
        // #31737 下单针对于非复杂交易的价格需要判断是否需免税
        if (in_array($item['transaction_type'], [self::TRANSACTION_NORMAL, self::TRANSACTION_SPOT]) && $item['product_type'] == ProductType::NORMAL) {
            [$currentPrice, $productPricePer, $serviceFeePer] = app(ProductPriceRepository::class)
                ->getProductTaxExemptionPrice(intval($item['seller_id']), $currentPrice, $serviceFeePer);
        }

        // #22763 大客户-折扣 #31737 优化逻辑 （普通商品，普通购买，非精细化）
        $useDiscountInfo = null;
        if ((!$item['vip_price'] || $useWkProQuotePrice) && $item['product_type'] == self::PRODUCT_NORMAL && $item['transaction_type'] == self::TRANSACTION_NORMAL) {
            $currentPriceTmp = $currentPrice;
            $currentPrice = MoneyHelper::upperAmount($currentPrice * ($useWkProQuotePrice ? $discountSpotRate : $discountRate), $precision);
            $discountPrice = $currentPriceTmp - $currentPrice;

            // 重新整理计算服务费和货值
            $productPricePer = MoneyHelper::upperAmount($productPricePer * ($useWkProQuotePrice ? $discountSpotRate : $discountRate), $precision);
            $serviceFeePer = $currentPrice - $productPricePer;
            $useDiscountInfo = $useWkProQuotePrice ? $discountSpotInfo : $discountInfo;
        }

        return [$currentPrice, $spotPrice ?? 0, $discountPrice ?? 0, $productPricePer, $serviceFeePer, $normalPrice, $useDiscountInfo];
    }

    /**
     * 计算一个产品的总价
     * @param $price
     * @param $packageFeePer
     * @param $quantity
     * @param $freightPer
     * @param bool $isCollectionFromDomicile
     * @return float|int
     */
    protected function calculateProductTotalPrice($price, $packageFeePer, $quantity, $freightPer, $isCollectionFromDomicile)
    {
        //上门取货的运费仅为打包费
        if ($isCollectionFromDomicile) {
            $total = ($price + $packageFeePer) * $quantity;
        } else {
            $total = ($price + $freightPer + $packageFeePer) * $quantity;
        }
        return $total;
    }

    public function getCwfTotalPrice($price, $packageFeePer, $quantity, $freightPer, $isCollectionFromDomicile)
    {
        return $this->calculateProductTotalPrice(...func_get_args());
    }

    /**
     * 获取产品的属性
     * @param int $productId
     * @return string
     */
    public function getProductColor($productId)
    {
        //select商品属性
        $colorArr = [];
        $color = $this->model_catalog_product->getAssociateProduct($productId);
        foreach ($color as $k => $v) {
            $colorArr[$v['associate_product_id']] = $v['name'];
        }
        return $colorArr[$productId] ?? '';
    }

    /**
     *处理图片尺寸
     * @param string $productImage
     * @return string|null
     * @throws Exception
     */
    public function handleProductImage($productImage)
    {
        $this->load->model('tool/image');
        $imageModel = $this->model_tool_image;
        $image = null;
        if ($productImage) {
            $image = $imageModel->resize($productImage, $this->config->get('theme_' . $this->config->get('config_theme') . '_image_cart_width'), $this->config->get('theme_' . $this->config->get('config_theme') . '_image_cart_height'));
        }
        if (!$image) {
            $image = $imageModel->resize('no_image.png', $this->config->get('theme_' . $this->config->get('config_theme') . '_image_cart_width'), $this->config->get('theme_' . $this->config->get('config_theme') . '_image_cart_height'));
        }
        return $image;
    }

    /**
     * 验证产品的上架库存
     * @param int $productId
     * @param int $buyQuantity
     * @param int $stockQuantity
     * @param int $productType
     * @param int $agreementId
     * @param int $transactionType
     * @return bool
     */
    public function isEnoughProductStock($productId, $buyQuantity, $stockQuantity, $productType, $agreementId, $transactionType)
    {
        // 对现货或期货尾款商品锁定库存校验
        $arr = [self::TRANSACTION_MARGIN, self::TRANSACTION_FUTURE];
        if (in_array($transactionType, $arr) && !in_array($productType, [self::PRODUCT_MARGIN, self::PRODUCT_FUTURE])) {
            $mapMargin = [
                'agreement_id' => $agreementId,
                'parent_product_id' => $productId,
                'type_id' => $transactionType
            ];
            $marginQty = $this->orm->table('oc_product_lock')->where($mapMargin)->selectRaw('round(qty/set_qty) as qty')->first();
            if (!$marginQty->qty || ($marginQty->qty < $buyQuantity)) {
                return false;
            }
        } else {
            // 对常规产品和补差价产品上架库存验证
            if (!$stockQuantity || ($stockQuantity < $buyQuantity)) {
                return false;
            }
        }
        // 购买数量不能为0
        if ($buyQuantity <= 0) {
            return false;
        }
        return true;
    }

    /**
     * 验证产品合法性
     * @param array $product
     * @param int $buyerId
     * @return bool
     * @throws Exception
     */
    public function validateProduct($product, $buyerId)
    {
        if (!$product['product_type'] && !$this->validateDelicacyProduct($product['product_id'], $buyerId, $product['seller_id'])) {
            return false;
        }
        if ($product['product_type'] && !$this->validateHeadProduct($product['product_id'], $product['agreement_id'], $product['product_type'], $buyerId)) {
            return false;
        }
        if ($product['transaction_type'] == self::TRANSACTION_REBATE && !$this->validateRebate($product['agreement_id'], $product['product_id'])) {
            return false;
        }
        if ($product['transaction_type'] == self::TRANSACTION_SPOT && !$this->validateSpot($product['agreement_id'], $product['quantity'])) {
            return false;
        }
        if ($product['product_type'] == self::PRODUCT_NORMAL && $product['transaction_type'] == self::TRANSACTION_FUTURE && !$this->validateFuture($product['agreement_id'], $product['quantity'])) {
            return false;
        }
        if ($product['product_type'] == self::PRODUCT_NORMAL
            && $product['transaction_type'] == self::TRANSACTION_MARGIN
            && !app(MarginRepository::class)->checkAgreementIsValid(intval($product['agreement_id']), intval($product['product_id']))
        ) {
            return false;
        }
        return true;
    }

    /**
     * 精细化验证
     * @param int $productId
     * @param int $buyerId
     * @param int $sellerId
     * @return bool
     */
    public function validateDelicacyProduct($productId, $buyerId, $sellerId)
    {
        // 获取精细化管理价格
        $fineData = $this->cart->getDelicacyManagementInfoByNoView((int)$productId, (int)$buyerId, (int)$sellerId);
        // 查找是否是返点得精细化
        $exists = $this->orm->table('oc_rebate_agreement as a')
            ->join('oc_rebate_agreement_item as i', 'i.agreement_id', '=', 'a.id')
            ->join('oc_delicacy_management as dm', [['a.buyer_id', '=', 'dm.buyer_id'], ['a.seller_id', '=', 'dm.seller_id'], ['i.product_id', '=', 'dm.product_id'], ['a.effect_time', '=', 'dm.effective_time']])
            ->where([
                ['a.status', '=', 3],
                ['a.buyer_id', '=', $buyerId],
                ['dm.product_id', '=', $productId],
                ['a.expire_time', '>', date('Y-m-d H:i:s')],
                ['dm.product_display', '=', 1]
            ])
            ->exists();
        if ($fineData && !$exists) {
            if ($fineData['product_display'] != 1) {
                return false;
            }
        }
        return true;
    }

    /**
     * 返点校验
     * @param int $agreementId
     * @param int $productId
     * @return bool
     */
    public function validateRebate($agreementId, $productId)
    {
        $rebate = $this->cart->getRebateInfo($agreementId, $productId);
        if (isset($rebate['is_valid']) && $rebate['is_valid'] == 0) {
            return false;
        }
        return true;
    }

    /**
     * 校验议价协议
     * @param $agreementId
     * @param $quantity
     * @return bool
     * @throws Exception
     */
    public function validateSpot($agreementId, $quantity)
    {
        $quote = $this->orm->table('oc_product_quote')
            ->select('quantity', 'status')
            ->find($agreementId);
        if (!$quote) {
            return false;
        }
        if ($quote->status != SpotProductQuoteStatus::APPROVED) {
            throw new Exception("This Spot Price Agreement has expired. Additional purchases cannot be processed, please review this agreement’s details in your Bid List.");
        }
        if ($quote->quantity != $quantity) {
            throw new Exception("The quantity of products purchased must match the quantity specified in this Spot Price Agreement. Note: Any specified quantities from this agreement may only be purchased once.", 10001);
        }
        // 议价判断不能重复创建订单
        $existsOrder = $this->orm->table('oc_order as oo')
            ->leftJoin('oc_order_product as op', 'oo.order_id', '=', 'op.order_id')
            ->where(['type_id' => ProductTransactionType::SPOT, 'agreement_id' => $agreementId, 'order_status_id' => OcOrderStatus::TO_BE_PAID])
            ->exists();
        if ($existsOrder) {
            throw new Exception("This Agreement has already been completed, you are no longer able to purchase additional items at the Agreement price.  For details, visit Purchase Order Management.");
        }

        return true;
    }

    public function validateFuture($agreementId, $quantity)
    {
        $agreement = FuturesMarginAgreement::query()
            ->select(['num', 'version'])
            ->where('id', $agreementId)
            ->first();
        if ($agreement->version == 3 && $agreement->num != $quantity) {
            throw new Exception("The qty of Agreement purchases is not enough.");
        }
        return true;
    }

    /**
     * 更新关联议价协议的购物车数量为协议的数量
     * @param int $cartId
     * @param $agreementId
     * @return false|int
     */
    public function resetCartSpotQuantity($cartId, $agreementId)
    {
        $quote = $this->orm->table('oc_product_quote')
            ->select('quantity', 'status')
            ->find($agreementId);
        if (!$quote) {
            return false;
        }

        return $this->orm->table('oc_cart')->where('cart_id', $cartId)->update(['quantity' => $quote->quantity]);
    }

    /**
     * 头款产品的验证
     * @param int $productId
     * @param int $agreementId
     * @param int $productType
     * @param int $buyerId
     * @return bool
     * @throws Exception
     */
    public function validateHeadProduct($productId, $agreementId, $productType, $buyerId)
    {
        if ($productType == self::PRODUCT_MARGIN) {
            return $this->validateMarginHeadProduct($productId, $agreementId, $buyerId);
        } elseif ($productType == self::PRODUCT_FUTURE) {
            return $this->validateFutureHeadProduct($productId, $buyerId);
        }
        return true;
    }

    /**
     * 现货头款产品的验证
     * @param int $productId
     * @param int $agreementId
     * @param int $buyerId
     * @return bool
     */
    public function validateMarginHeadProduct($productId, $agreementId, $buyerId)
    {
        $agreement_code = $this->orm->table('tb_sys_margin_process')
            ->where([
                'process_status' => 1,
                'advance_product_id' => $productId,
            ])
            ->value('margin_agreement_id');
        //验证是否是履约人购买的
        if ($agreement_code) {
            $performer_flag = $this->orm->table('tb_sys_margin_agreement')
                ->where(
                    [
                        'id' => $agreementId,
                        'buyer_id' => $buyerId,
                    ]
                )
                ->value('id');
        } else {
            $performer_flag = $this->orm->table('oc_agreement_common_performer')
                ->where(
                    [
                        'agreement_id' => $agreementId,
                        'agreement_type' => $this->config->get('common_performer_type_margin_spot'),
                        'buyer_id' => $buyerId,
                    ]
                )
                ->value('id');
        }
        if (!$performer_flag) {
            return false;
        }
        return true;
    }

    /**
     * 期货头款产品的验证
     * @param int $productId
     * @param int $buyerId
     * @return bool
     * @throws Exception
     */
    public function validateFutureHeadProduct($productId, $buyerId)
    {
        $this->load->model('futures/agreement');
        //判断是不是期货头款
        $info = $this->orm->table('oc_futures_margin_process as fp')
            ->leftJoin('oc_futures_margin_agreement as fa', 'fa.id', 'fp.agreement_id')
            ->where([
                'fp.process_status' => 1,
                'fp.advance_product_id' => $productId,
                'fa.agreement_status' => 3,
            ])
            ->select('fa.agreement_no', 'fa.buyer_id')
            ->first();
        if ($info && $info->buyer_id != $buyerId) {//期货头款商品 但不属于该用户的期货协议
            return false;
        }
        return true;
    }

    public function getSubTotal($products = [])
    {
        $total = 0;
        foreach ($products as $product) {
            $total += $product['current_price'] * $product['quantity'];
        }
        return $total;
    }

    public function getRealTotal($products = [])
    {
        $total = 0;
        foreach ($products as $product) {
            $total += $product['realTotal'];
        }
        return $total;
    }

    public function getRealSubTotal($products = [])
    {
        $total = 0;
        foreach ($products as $product) {
            $total += round($product['product_price_per'], 2) * $product['quantity'];
        }
        return $total;
    }


    /**
     * 判断云送仓的购物车与填写的云送仓信息是否匹配
     * @param array $products
     * @return bool
     * @throws Exception
     */
    public function checkCloudLogisticsOrder(array $products = [])
    {
        //没有找到云送仓填写的订单信息
        if (!empty($this->session->get('cwf_id'))) {
            //查看云送仓的填写的信息
            $this->load->model('checkout/cwf_info');
            $cloudItems = $this->model_checkout_cwf_info->getCloudLogisticsItems($this->session->get('cwf_id'));
            $diff_arr = array_udiff($cloudItems, $products, "compare_array");
            if (isset($diff_arr) && count($diff_arr) > 0) {
                return false;
            } else {
                return true;
            }
        } else {
            return false;
        }
    }

    /**
     * 获取协议的编码
     * @param $agreementId
     * @param $transactionType
     * @return Builder|mixed|null
     */
    public function getAgreementCode($agreementId, $transactionType)
    {
        switch ($transactionType) {
            case self::TRANSACTION_REBATE:
                $table = 'oc_rebate_agreement';
                $column = 'agreement_code';
                break;
            case self::TRANSACTION_MARGIN:
                $table = 'tb_sys_margin_agreement';
                $column = 'agreement_id';
                break;
            case self::TRANSACTION_FUTURE:
                $table = 'oc_futures_margin_agreement';
                $column = 'agreement_no';
                break;
            case self::TRANSACTION_SPOT:
                $table = 'oc_product_quote';
                $column = 'agreement_no';
                break;
        }
        if (isset($table)) {
            return $this->orm->table($table)
                ->where('id',$agreementId)
                ->value($column);
        }
        return null;
    }
}
