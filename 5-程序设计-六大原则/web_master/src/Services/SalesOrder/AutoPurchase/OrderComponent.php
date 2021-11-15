<?php

namespace App\Services\SalesOrder\AutoPurchase;

use App\Enums\Pay\PayCode;
use App\Enums\Product\ProductTransactionType;
use App\Enums\Product\ProductType;
use App\Exception\SalesOrder\AutoPurchaseException;
use App\Exception\SalesOrder\Enums\AutoPurchaseCode;
use App\Logging\Logger;
use App\Models\Cart\Cart;
use App\Models\Customer\Customer;
use App\Models\FeeOrder\FeeOrder;
use App\Models\Futures\FuturesMarginAgreement;
use App\Models\Margin\MarginAgreement;
use App\Models\Product\Product;
use App\Repositories\FeeOrder\FeeOrderRepository;
use App\Repositories\SalesOrder\AutoBuyRepository;
use App\Services\Marketing\CampaignService;
use App\Services\Marketing\CouponService;
use App\Services\Quote\QuoteService;
use Exception;

class OrderComponent
{
    private $saleOrderId;
    private $feeOrderId;
    private $customerId;
    private $deliveryType;

    /**
     * OrderComponent constructor.
     * @param int $saleOrderId
     * @param int $feeOrderId
     * @throws Exception
     */
    public function __construct(int $saleOrderId, int $feeOrderId)
    {
        $this->saleOrderId = $saleOrderId;
        $this->feeOrderId = $feeOrderId;
        $this->customerId = customer()->getId();
        $this->deliveryType = session('delivery_type', 0);

        $this->validate();
    }

    /**
     * 处理订单
     * @return int
     * @throws Exception
     */
    public function handle(): int
    {
        Logger::autoPurchase('----AUTO PURCHASE ORDER START----', 'info', [
            Logger::CONTEXT_VAR_DUMPER => ['saleOrderId' => $this->saleOrderId, 'feeOrderId' => $this->feeOrderId],
        ]);

        // 获取购物车ID
        $cartIds = $this->getCartIds();

        // 支付方式
        [$paymentCode, $paymentMethod] = (app(AutoBuyRepository::class))->getPaymentCodeAndMethod();

        // 获取购物车的产品
        $products = $this->getCartProducts($cartIds);

        // 商品的总价值
        $productsTotal = !empty($products) ? array_sum(array_column($products, 'total')) : 0;

        // 费用单计算金额
        if ($this->feeOrderId) {
            $productsTotal += app(FeeOrderRepository::class)->findFeeOrderTotal([$this->feeOrderId]);
        }

        $customer = Customer::query()->findOrFail($this->customerId);
        $credit = $customer->line_of_credit;
        if (PayCode::PAY_LINE_OF_CREDIT == $paymentCode && $credit < $productsTotal) {
            throw new AutoPurchaseException(AutoPurchaseCode::CODE_购买失败_信用额度不足);
        }

        // 开始处理购买逻辑
        $orderId = 0;
        $total = 0;
        /** @var \ModelCheckoutOrder $modelCheckoutOrder */
        $modelCheckoutOrder = load()->model('checkout/order');
        if (!empty($products)) {
            // 原逻辑中的orderTotalShow 与下方的计算total_data 逻辑重复， 即totalData和total_data
            /** @var \ModelAccountCartCart $modelAccountCartCart */
            $modelAccountCartCart = load()->model('account/cart/cart');
            $totalData = $modelAccountCartCart->orderTotalShow($products);

            $total = $totalData['total']['value'];
            $totals = $totalData['totals'];
            if (PayCode::PAY_LINE_OF_CREDIT == $paymentCode && $credit < $total) {
                throw new AutoPurchaseException(AutoPurchaseCode::CODE_购买失败_信用额度不足);
            }
            if (!app(AutoBuyRepository::class)->checkSubToal($totalData, $products, customer()->isEurope())) {
                throw new AutoPurchaseException(AutoPurchaseCode::CODE_购买失败_支付金额计算有误);
            }

            $codeTotalsMap = array_column($totals, null, 'code');
            // 促销活动
            $campaigns = $codeTotalsMap['promotion_discount']['discounts'] ?? [];
            // 总货值金额+总服务费
            $subItemsAndServiceFeeTotal = bcadd($codeTotalsMap['sub_total']['value'], $codeTotalsMap['service_fee']['value'] ?? 0, 4);
            // 优惠券
            $couponId = $totalData['select_coupon_ids'][0] ?? 0;

            $precision = customer()->isJapan() ? 0 : 2;
            // 计算每个产品占用满减份额
            $campaignPerDiscount = app(CampaignService::class)->calculateMutiCampaignDiscount($campaigns, $products, $precision);
            // 计算每个产品占用优惠券份额
            $couponPerDiscount = [];
            if (isset($codeTotalsMap['giga_coupon']) && $codeTotalsMap['giga_coupon']) {
                $couponPerDiscount = app(CouponService::class)->calculateCouponDiscount(abs($codeTotalsMap['giga_coupon']['value']), $products, $precision);
            }

            $orderData = $this->createOrderData($products, $totalData, $customer, $paymentMethod, $paymentCode, $couponPerDiscount, $campaignPerDiscount);
            $orderId = $modelCheckoutOrder->addOrder($orderData, $this->saleOrderId);

            // 议价相关数据插入
            app(QuoteService::class)->addOrderQuote($products, $customer->customer_id, $orderId, customer()->getCountryId(), customer()->isEurope());
            $orderData = [
                'order_id' => $orderId,
                'balance' => PayCode::PAY_VIRTUAL == $paymentCode ? 0 : $total,
                'poundage' => 0,
                'order_total' => $total,
            ];

            /** @var \ModelCheckoutPay $modelCheckoutPay */
            $modelCheckoutPay = load()->model('checkout/pay');
            $modelCheckoutPay->updateOrderTotal($orderData);
        }

        // 修改费用单支付方式
        $feeOrderIdArr = empty($this->feeOrderId) ? [] : [$this->feeOrderId];
        $modelCheckoutOrder->updateFeeOrderPayment($feeOrderIdArr, $paymentCode, $paymentMethod);

        // 支付逻辑
        if (PayCode::PAY_VIRTUAL == $paymentCode) {
            /** @var \ModelAccountBalanceVirtualPayRecord $modelAccountBalanceVirtualPayRecord */
            $modelAccountBalanceVirtualPayRecord = load()->model('account/balance/virtual_pay_record');
            if (!empty($orderId)) {
                $modelAccountBalanceVirtualPayRecord->insertData($this->customerId, $orderId, $total);
            }
            if (!empty($this->feeOrderId)) {
                $feeOrder = FeeOrder::query()->findOrFail($this->feeOrderId);
                $modelAccountBalanceVirtualPayRecord->insertData($this->customerId, $feeOrder->id, $feeOrder->fee_total, 4);
            }
        } else {
            // 组合支付的时候
            $modelCheckoutOrder->payByLineOfCredit($orderId, $feeOrderIdArr, $this->customerId);
        }

        if (!empty($products)) {
            //库存预出库
            $modelCheckoutOrder->withHoldStock($orderId);
            // 优惠券设置为已使用
            app(CouponService::class)->setCouponUsed($orderId, $couponId, $subItemsAndServiceFeeTotal);
            // 记录订单参与的促销活动
            app(CampaignService::class)->addCampaignOrder(array_merge($campaigns, $totalData['gifts']), $orderId);
        }

        //自动购买，目前只支持自营商品，购买完成，订单完毕。状态码5
        $modelCheckoutOrder->addOrderHistoryByYzcModel($orderId, $feeOrderIdArr, 5, '', false, false, $this->saleOrderId);

        Logger::autoPurchase('----AUTO PURCHASE ORDER END----', 'info', [
            Logger::CONTEXT_VAR_DUMPER => ['orderId' => $orderId],
        ]);

        return $orderId;
    }

    /**
     * @param array $products
     * @param array $totalData
     * @param Customer $customer
     * @param $paymentMethod
     * @param $paymentCode
     * @param $couponPerDiscount
     * @param $campaignPerDiscount
     * @return array
     * @throws Exception
     */
    private function createOrderData(array $products, array $totalData, Customer $customer, $paymentMethod, $paymentCode, $couponPerDiscount, $campaignPerDiscount): array
    {
        $orderData = [
            'totals' => $totalData['all_totals'],
            'invoice_prefix' => configDB('config_invoice_prefix', 0),
            'store_id' => config('config_store_id', 0),
            'store_name' => configDB('config_name', 'B2B.GIGACLOUDLOGISTICS'),
            'customer_id' => $customer->customer_id,
            'customer_group_id' => $customer->customer_group_id,
            'firstname' => $customer->firstname,
            'lastname' => $customer->lastname,
            'email' => $customer->email,
            'telephone' => $customer->telephone,
            'custom_field' => json_decode($customer->custom_field, true),
            'payment_method' => $paymentMethod,
            'payment_code' => $paymentCode,
        ];

        if ($orderData['store_id']) {
            $orderData['store_url'] = config()->get('config_url');
        } else {
            $orderData['store_url'] = request()->server('HTTPS') ? HTTPS_SERVER : HTTP_SERVER;
        }

        // 此逻辑不知道还需不需要了
        /** @var \ModelAccountAddress $modelAccountAddress */
        $modelAccountAddress = load()->model('account/address');
        $paymentAddress = $modelAccountAddress->getAddressWithBuyerId($customer->address_id, $customer->customer_id);
        $orderData['payment_firstname'] = $paymentAddress['firstname'];
        $orderData['payment_lastname'] = $paymentAddress['lastname'];
        $orderData['payment_company'] = $paymentAddress['company'];
        $orderData['payment_address_1'] = $paymentAddress['address_1'];
        $orderData['payment_address_2'] = $paymentAddress['address_2'];
        $orderData['payment_city'] = $paymentAddress['city'];
        $orderData['payment_postcode'] = $paymentAddress['postcode'];
        $orderData['payment_zone'] = $paymentAddress['zone'];
        $orderData['payment_zone_id'] = $paymentAddress['zone_id'];
        $orderData['payment_country'] = $paymentAddress['country'];
        $orderData['payment_country_id'] = $paymentAddress['country_id'];
        $orderData['payment_address_format'] = $paymentAddress['address_format'];
        $orderData['payment_custom_field'] = (isset($paymentAddress['custom_field']) ? $paymentAddress['custom_field'] : array());


        foreach ($products as $product) {
            $discount = null;
            if ($product['type_id'] == ProductTransactionType::NORMAL && $product['product_type'] == ProductType::NORMAL) {
                $discount = $product['discount_info']->discount ?? null; //产品折扣
                $product['discount_info'] && $product['discount_info']->buy_qty = $product['quantity'];
            } elseif ($product['type_id'] == ProductTransactionType::MARGIN) {
                $agreement = MarginAgreement::query()->where('id', $product['agreement_id'])->first(['discount', 'discount_price', 'product_id', 'is_bid']); //产品折扣
                $discount = $agreement->discount;
                $product['discount_price'] = $agreement->discount_price;
            } elseif ($product['type_id'] == ProductTransactionType::FUTURE) {
                $agreement = FuturesMarginAgreement::query()->where('id', $product['agreement_id'])->first(['discount', 'discount_price', 'product_id', 'is_bid']); //产品折扣
                $discount = $agreement->discount;
                $product['discount_price'] = $agreement->discount_price;
            }

            $orderData['products'][] = array(
                'product_id' => $product['product_id'],
                'name' => $product['name'],
                'model' => $product['model'],
                'option' => [], // 有个option的数据，不需要了
                'download' => $product['download'],
                'quantity' => $product['quantity'],
                'subtract' => $product['subtract'],
                'total' => round($product['price'], 2) * $product['quantity'],
                'price' => round($product['product_price_per'], 2),//使用货值，即上门取货价
                'serviceFeePer' => round($product['service_fee_per'], 2),
                'serviceFee' => round($product['service_fee_per'], 2) * $product['quantity'],
                'tax' => 0, // 目前好像没用
                'reward' => $product['reward'] ?? 0,
                'freight_per' => $product['freight_per'],//基础运费+超重附加费
                'base_freight' => $product['base_freight'],//基础运费
                'overweight_surcharge' => $product['overweight_surcharge'],//超重附加费
                'coupon_amount' => $couponPerDiscount[$product['product_id']]['discount'] ?? 0, //优惠券金额
                'campaign_amount' => $campaignPerDiscount[$product['product_id']]['discount'] ?? 0, //满减金额
                'package_fee' => $product['package_fee_per'], //打包费
                'subProducts' => $product['sub_products'],
                'discount' => $discount, //产品折扣
                'discount_price' => $product['discount_price'] ?? 0, //产品折扣
                'discount_info' => $product['discount_info'],
                'cart_id' => $product['cart_id'],
                'type_id' => $product['type_id'],
                'agreement_id' => $product['agreement_id'],
                'danger_flag' => $product['danger_flag'] ?? 0,
            );
        }

        // 有个vouchers的逻辑session('vouchers')，不需要了， 需确认
        // 有个comment的逻辑$this->session->get('comment')， 不需要了， 需确认
        $currencyInfo = obj2array(db('oc_currency')->where('code', session('currency'))->first());

        $orderData['total'] = $totalData['total']['value'];
        $orderData['affiliate_id'] = 0;
        $orderData['commission'] = 0;
        $orderData['marketing_id'] = 0;
        $orderData['tracking'] = '';
        $orderData['language_id'] = config()->get('config_language_id', 1);
        $orderData['currency_id'] = $currencyInfo['currency_id'];
        $orderData['currency_code'] = $currencyInfo['code'];
        // 记录当前交易币种的汇率（美元是对美元的汇率，为1；英镑和欧元是对人民币的汇率，每日维护）
        $orderData['current_currency_value'] = $currencyInfo['value'];
        // 修改oc_order表currency_value字段始终为1，该字段涉及account/order展示价格的部分
        $orderData['currency_value'] = 1;
        $orderData['ip'] = request()->server('REMOTE_ADDR');
        $orderData['forwarded_ip'] = request()->server('HTTP_X_FORWARDED_FOR') ?: request()->server('HTTP_CLIENT_IP', '');
        $orderData['user_agent'] = request()->server('HTTP_USER_AGENT', '');
        $orderData['accept_language'] = request()->server('HTTP_ACCEPT_LANGUAGE', '');

        return $orderData;
    }

    /**
     * 获取购物车产品
     * @param array $cartIds
     * @return array
     * @throws Exception
     */
    private function getCartProducts(array $cartIds): array
    {
        if (empty($cartIds)) {
            return [];
        }

        $libraryCart = new \Cart\Cart(app('registry'));
        $products = $libraryCart->getProducts($this->customerId, $this->deliveryType, $cartIds);

        // 校验商品在库库存是否满足需求
        // 忽略尾款产品的校验 尾款产品的校验放在出库
        $resolvedProduct = [];
        foreach ($products as $key => $product) {
            if (!configDB('config_stock_checkout', 0) && !$product['stock']) {
                throw new AutoPurchaseException(AutoPurchaseCode::CODE_购买失败_库存不足, $product['sku']);
            }
            if ($product['minimum'] > $product['quantity']) {
                throw new AutoPurchaseException(AutoPurchaseCode::CODE_购买失败_库存不足, $product['sku']);
            }

            if (!in_array($product['product_type'], ProductType::deposit()) && in_array($product['type_id'], ProductTransactionType::notUsedDepositTypes())) {
                $resolvedProduct[] = $product;
            }

            // 保持数据的一致性
            if (!isset($product['quote_amount']) && isset($product['spot_price'])) {
                $products[$key]['quote_amount'] = $product['spot_price'];
            }
            if (!isset($product['spot_price']) && isset($product['quote_amount'])) {
                $products[$key]['spot_price'] = $product['quote_amount'];
            }
            if (!isset($product['price']) && isset($product['current_price'])) {
                $products[$key]['price'] = $product['current_price'];
            }
            if (!isset($product['current_price']) && isset($product['price'])) {
                $products[$key]['current_price'] = $product['price'];
            }
            if (!isset($product['transaction_type']) && isset($product['type_id'])) {
                $products[$key]['transaction_type'] = $product['type_id'];
            }
            if (!isset($product['type_id']) && isset($product['transaction_type'])) {
                $products[$key]['type_id'] = $product['transaction_type'];
            }
        }

        $this->checkProductQuantityValid($resolvedProduct);

        return $products;
    }

    /**
     * 自动购买库存
     * ps:该方法自动忽略了对于非普通商品的校验
     * 主要是原先的不满足哪个sku库存不足
     * @throws AutoPurchaseException
     * @see ModelCommonProduct::checkProductQuantityValid
     */
    private function checkProductQuantityValid(array $products)
    {
        /** @var \ModelCommonProduct $modelCommonProduct */
        $modelCommonProduct = load()->model('common/product');

        Logger::autoPurchase('----库存校验开始----');

        $productIds = array_column($products, 'product_id');
        $productIdsMap = Product::query()->whereIn('product_id', $productIds)->get()->keyBy('product_id');

        $resolved = [];
        foreach ($products as $p) {
            if (!$product = $productIdsMap->get($p['product_id'], '')) {
                continue;
            }
            /** @var Product $product */
            if (!in_array($product->product_type, [ProductType::NORMAL, ProductType::COMPENSATION_FREIGHT])) {
                continue;
            }
            if (array_key_exists('type_id', $p) && in_array((int)$p['type_id'], [2, 3])) {
                continue;
            }
            $quantity = (int)$p['quantity'];

            $combos = $modelCommonProduct->getComboProduct($product->product_id);
            if (empty($combos)) {
                if (array_key_exists($product->product_id, $resolved)) {
                    $resolved[$product->product_id] += $quantity;
                } else {
                    $resolved[$product->product_id] = $quantity;
                }
            } else {
                foreach ($combos as $c) {
                    $son_product_id = (int)$c['product_id'];
                    $son_quantity = (int)($quantity * $c['qty']);
                    if (array_key_exists($son_product_id, $resolved)) {
                        $resolved[$son_product_id] += $son_quantity;
                    } else {
                        $resolved[$son_product_id] = $son_quantity;
                    }
                }
            }
        }
        foreach ($resolved as $id => $qty) {
            Logger::autoPurchase('PRODUCT ID:' . $id);
            $batchList = db('tb_sys_batch')
                ->where('product_id', $id)
                ->where('onhand_qty', '>', 0)
                ->lockForUpdate()
                ->get();
            Logger::autoPurchase(['BATCH INFO END', 'BATCH INFO' => $batchList]);
            $lock_qty = $modelCommonProduct->getProductOriginLockQty($id);
            $in_stock_qty = $modelCommonProduct->getProductInStockQuantity($id);
            Logger::autoPurchase("Product in stock:{$in_stock_qty} lock:{$lock_qty} require:{$qty}");
            if ($in_stock_qty - $lock_qty < $qty) {
                /** @var Product $product */
                $product = $productIdsMap->get($id);
                throw new AutoPurchaseException(AutoPurchaseCode::CODE_购买失败_库存不足, $product->sku);
            }
        }

        Logger::autoPurchase('----库存校验结束----');
    }

    /**
     * 获取购物车ID
     * @return array
     * @throws Exception
     */
    private function getCartIds(): array
    {
        $cartIds = Cart::query()->where('api_id', 1)->where('customer_id', $this->customerId)->pluck('cart_id')->toArray();

        // 内部自动购买采销异体账号
        if (customer()->innerAutoBuyAttr1()) {
            /** @var \ModelCheckoutCart $modelCheckoutCart */
            $modelCheckoutCart = load()->model('checkout/cart');

            // 匹配购物车商品和New Order销售订单
            if (empty($modelCheckoutCart->checkPurchaseAndSales($this->deliveryType)) && empty($this->feeOrderId)) {
                throw new AutoPurchaseException(AutoPurchaseCode::CODE_购买失败_购物车的产品和销售订单的产品不一致);
            }

            // 修改自动购买采销异体账号的购物车
            $cartIds = $modelCheckoutCart->updateInnerAutoBuyCart($this->deliveryType);
            if (empty($cartIds) && empty($this->feeOrderId)) {
                throw new AutoPurchaseException(AutoPurchaseCode::CODE_购买失败_购物车无数据);
            }
        }

        // 校验要购买的明细的是否在销售订单明细范围之内
        app(AutoBuyRepository::class)->checkSaleOder($cartIds, $this->customerId, $this->saleOrderId);

        return $cartIds;
    }

    /**
     * 校验
     * @throws Exception
     */
    private function validate()
    {
        if (!empty($this->feeOrderId)) {
            $feeOrderInfos = app(FeeOrderRepository::class)->findFeeOrderInfo([$this->feeOrderId]);
        }

        if (Cart::query()->where('api_id', 1)->where('customer_id', $this->customerId)->doesntExist() && empty($feeOrderInfos)) {
            throw new AutoPurchaseException(AutoPurchaseCode::CODE_购买失败_没有需要购买的产品或费用单);
        }
    }
}
