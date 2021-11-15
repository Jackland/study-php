<?php

use App\Components\Locker;
use App\Enums\Common\YesNoEnum;
use App\Enums\Customer\CustomerAccountingType;
use App\Enums\FeeOrder\FeeOrderStatus;
use App\Enums\Order\OcOrderStatus;
use App\Enums\Pay\PayCode;
use App\Enums\Product\ProductTransactionType;
use App\Enums\Product\ProductType;
use App\Exception\AssociatedPreException;
use App\Logging\Logger;
use App\Models\FeeOrder\FeeOrder;
use App\Models\Futures\FuturesMarginAgreement;
use App\Models\Futures\FuturesMarginDelivery;
use App\Models\Margin\MarginAgreement;
use App\Models\Marketing\Coupon;
use App\Models\Marketing\MarketingTimeLimitProduct;
use App\Models\Product\Product;
use App\Models\SalesOrder\CustomerSalesOrder;
use App\Repositories\FeeOrder\FeeOrderRepository;
use App\Repositories\Margin\MarginRepository;
use App\Repositories\Marketing\MarketingDiscountRepository;
use App\Repositories\Order\OrderRepository;
use App\Repositories\Safeguard\SafeguardConfigRepository;
use App\Repositories\Seller\SellerRepository;
use App\Services\FeeOrder\FeeOrderService;
use App\Services\Marketing\CouponService;
use App\Services\Marketing\CampaignService;
use App\Services\Quote\QuoteService;
use App\Services\Stock\BuyerStockService;
use Carbon\Carbon;
use Framework\Exception\Http\NotFoundException;

/**
 * Class ControllerCheckoutConfirm
 *
 * @property ModelCheckoutOrder $model_checkout_order
 * @property ModelSettingExtension $model_setting_extension
 * @property ModelAccountCustomer model_account_customer
 * @property ModelCheckoutPay $model_checkout_pay
 * @property ModelToolImage $model_tool_image
 * @property ModelAccountCustomerOrder $model_account_customer_order
 * @property ModelCommonProduct $model_common_product
 * @property ModelBuyerBuyerCommon $model_buyer_buyer_common
 * @property ModelAccountSalesOrderMatchInventoryWindow $model_account_sales_order_match_inventory_window;
 * @property ModelAccountCartCart $model_account_cart_cart
 * @property ModelFuturesAgreement $model_futures_agreement
 */
class ControllerCheckoutConfirm extends Controller
{
    const START_ORDER = '----START ORDER----';
    const END_ORDER = '----END ORDER----';
    const CAN_VIRTUAL = 1;
    protected $modelPreOrder;
    protected $couponService;

    public function __construct(Registry $registry, ModelCheckoutPreOrder $modelCheckoutPreOrder, CouponService $couponService)
    {
        parent::__construct($registry);

        if (!$this->customer->isLogged()) {
            session()->set('redirect', $this->url->link('account/account', '', true));
            $this->response->redirect($this->url->link('account/login', '', true));
        }
        $this->modelPreOrder = $modelCheckoutPreOrder;
        $this->couponService = $couponService;
        $this->load->model('common/product');
    }

    public function createOrder()
    {
        $currency = $this->session->get('currency');
        $deliveryType = $this->request->post('delivery_type', 0);
        $couponId = $this->request->post('coupon_id', 0);
        if (!app(CouponService::class)->checkCouponCanUsed($couponId)) {
            $couponId = 0;
        }
        $preOrderTotal = $this->request->post('total', 0);
        $cwf_file_upload_id = request('cwf_file_upload_id');
        $customerId = $this->customer->getId();
        $isCollectionFromDomicile = $this->customer->isCollectionFromDomicile();
        $countryId = $this->customer->getCountryId();
        $originalProducts = $this->modelPreOrder->getPreOrderCache($this->request->post('cart_id_str'), $this->request->attributes->get('buy_now_data'));
        $products = $this->modelPreOrder->handleProductsData($originalProducts, $customerId, $deliveryType, $isCollectionFromDomicile, $countryId);
        $precision = $this->customer->getCountryId() == JAPAN_COUNTRY_ID ? 0 : 2;
        $redirect = '';
        $hasHeadProduct = false;
        $successFlag = false;
        $this->session->set('delivery_type', $deliveryType);
        // 开始记录order log
        Logger::order(static::START_ORDER, 'info', [
            Logger::CONTEXT_WEB_SERVER_VARS => ['_REQUEST', '_SERVER'],
            Logger::CONTEXT_VAR_DUMPER => ['products' => $products],
        ]);
        // 判断$products中是否存在现货或期货头款商品
        $productTypes = array_column($products, 'product_type');
        if (in_array(1, $productTypes) || in_array(2, $productTypes)) {
            $hasHeadProduct = true;
        }
        $lock = Locker::checkoutConfirmCreateOrder(1, 60);
        // 只有存在期货 现货头款产品才会进行文件锁
        if ((!$hasHeadProduct || $lock->acquire(true))
            && $checkOrderResult = $this->validateBeforeCreateOrder($products, $customerId, $deliveryType, $cwf_file_upload_id)) {
            try {
                $this->db->beginTransaction();
                $this->load->model('account/cart/cart');
                $this->load->model('account/customer');
                $this->load->model('checkout/order');
                $totalData = $this->model_account_cart_cart->orderTotalShow($products, true, ['coupon_ids' => (empty($couponId) ? [] : [$couponId])]);
                $customerInfo = $this->model_account_customer->getCustomer($customerId);
                // 判断下单页面金额和此时金额是否一致
                if (bccomp($totalData['total']['value'], $preOrderTotal) !== 0) {
                    return $this->jsonFailed('The current price is invalid. Please refresh the page and try again.', [], 405);
                }
                $orderData = [
                    'totals' => $totalData['all_totals'],
                    'invoice_prefix' => $this->config->get('config_invoice_prefix'),
                    'store_id' => $this->config->get('config_store_id'),
                    'store_name' => $this->config->get('config_name'),
                    'customer_id' => $customerId,
                    'customer_group_id' => $customerInfo['customer_group_id'],
                    'firstname' => $customerInfo['firstname'],
                    'lastname' => $customerInfo['lastname'],
                    'email' => $customerInfo['email'],
                    'telephone' => $customerInfo['email'],
                    'custom_field' => json_decode($customerInfo['custom_field'], true),
                ];
                if ($orderData['store_id']) {
                    $orderData['store_url'] = $this->config->get('config_url');
                } else {
                    if ($this->request->serverBag->get('HTTPS')) {
                        $orderData['store_url'] = HTTPS_SERVER;
                    } else {
                        $orderData['store_url'] = HTTP_SERVER;
                    }
                }

                $collection = collect($totalData['totals'])->keyBy('code');
                $campaigns = $collection['promotion_discount']['discounts'] ?? []; // 促销活动
                $subItemsTotal = $collection['sub_total']['value']; // 总货值金额
                $serviceFeeTotal = $collection['service_fee']['value'] ?? 0; // 总服务费
                $subItemsAndServiceFeeTotal = bcadd($subItemsTotal, $serviceFeeTotal, 4); //总货值金额+总服务费
                // 计算每个产品占用满减份额
                $campaignPerDiscount = app(CampaignService::class)->calculateMutiCampaignDiscount($campaigns, $products, $precision);
                // 判断是否使用优惠券
                if ($couponId) {
                    $coupon = Coupon::find($couponId);
                    // 计算每个产品占用优惠券份额
                    $couponPerDiscount = $this->couponService->calculateCouponDiscount($coupon->denomination, $products, $precision);
                }
                // 组合 oc_order_product的数据
                $orderData['products'] = array();
                // 获取纯物流运费
                $orderProductPureLogistics = app(OrderRepository::class)->handleOrderProductPureLogistics($products, $customerId);
                foreach ($products as $product) {
                    $discount = null;
                    $maxDiscount = null;
                    $discountInfo = null;
                    // 协议折扣失效判断
                    if ($product['transaction_type'] == ProductTransactionType::MARGIN) {
                        $agreement = MarginAgreement::query()->where('id', $product['agreement_id'])->first(['discount', 'discount_price', 'product_id', 'is_bid', 'num']); //产品折扣
                        $discountInfo = app(MarketingDiscountRepository::class)->getMaxDiscount($customerId, $agreement->product_id, $agreement->num, $product['transaction_type']);
                        $maxDiscount = $discountInfo->discount ?? null;
                        $discount = $agreement->discount;
                        $product['discount_price'] = $agreement->discount_price;
                        $discountInfo && $discountInfo->buy_qty = $agreement->num;
                    } elseif ($product['transaction_type'] == ProductTransactionType::FUTURE) {
                        $agreement = FuturesMarginAgreement::query()->where('id', $product['agreement_id'])->first(['discount', 'discount_price', 'product_id', 'is_bid', 'num']); //产品折扣
                        $discountInfo = app(MarketingDiscountRepository::class)->getMaxDiscount($customerId, $agreement->product_id, $agreement->num, $product['transaction_type']);
                        $maxDiscount = $discountInfo->discount ?? null;
                        $discount = $agreement->discount;
                        $product['discount_price'] = $agreement->discount_price;
                        $discountInfo && $discountInfo->buy_qty = $agreement->num;
                    } elseif ($product['transaction_type'] == ProductTransactionType::NORMAL && $product['product_type'] == ProductType::NORMAL) {
                        $discountInfo = $product['discount_info'];
                        $maxDiscount = $discountInfo->discount ?? null;
                        $discount = $maxDiscount;
                        $discountInfo && $discountInfo->buy_qty = $product['quantity'];
                    }
                    // 判断非bid协议的折扣是否失效
                    if ($maxDiscount != $discount && empty($agreement->is_bid)) {
                        // 协议时的折扣失效，将头款产品置为下架以及废弃状态
                        if (($product['product_type'] == ProductType::MARGIN_DEPOSIT && !FuturesMarginDelivery::query()->where('margin_agreement_id', $product['agreement_id'])->exists()) || $product['product_type'] == ProductType::FUTURE_MARGIN_DEPOSIT) {
                            // 协议时的折扣失效，将头款产品置为下架以及废弃状态
                            Product::query()->where('product_id', $product['product_id'])->update(['status' => YesNoEnum::NO, 'is_deleted' => YesNoEnum::YES]);
                            $this->db->commit();
                            return $this->jsonFailed('The discount set for this agreement has been invalid. Please sign a new agreement.', ['product_id' => $agreement->product_id ?? null], 406);
                        }
                    }
                    if ($discountInfo instanceof MarketingTimeLimitProduct && $discountInfo->qty < $discountInfo->buy_qty) {
                        return $this->jsonFailed(' Product not available in the desired quantity or not in stock!', [], 405);
                    }

                    $optionData = array();
                    $orderData['products'][] = array(
                        'product_id' => $product['product_id'],
                        'name' => $product['name'],
                        'model' => $product['model'],
                        'option' => $optionData,
                        'quantity' => $product['quantity'],
                        'subtract' => $product['subtract'],
                        'price' => round($product['product_price_per'], 2),//使用货值，即上门取货价
                        'serviceFeePer' => round($product['service_fee_per'], 2),
                        'serviceFee' => round($product['service_fee_per'], 2) * $product['quantity'],
                        'total' => round($product['current_price'], 2) * $product['quantity'],
                        'tax' => 0,
                        'freight_per' => $product['freight_per'],//单件运费（基础运费+超重附加费）
                        'base_freight' => $product['base_freight'],//基础运费
                        'overweight_surcharge' => $product['overweight_surcharge'],//超重附加费
                        'package_fee' => $product['package_fee_per'], //打包费
                        'coupon_amount' => $couponPerDiscount[$product['product_id']]['discount'] ?? 0, //优惠券金额
                        'campaign_amount' => $campaignPerDiscount[$product['product_id']]['discount'] ?? 0, //满减金额
                        'discount' => $discount, //产品折扣
                        'discount_price' => $product['discount_price'] ?? 0, //产品折扣
                        'discount_info' => $discountInfo,
                        'type_id' => $product['transaction_type'],
                        'agreement_id' => $product['agreement_id'],
                        'volume' => $product['volume'],
                        'volume_inch' => $product['volume_inch'],
                        'danger_flag' => $product['danger_flag'] ?? 0,
                        'is_pure_logistics' => intval($orderProductPureLogistics[$product['product_id']] ?? 0)
                    );
                }
                $orderData['total'] = $totalData['total']['value'];
                $orderData['language_id'] = $this->config->get('config_language_id');
                $orderData['currency_id'] = $this->currency->getId($currency);
                $orderData['currency_code'] = $currency;
                // 记录当前交易币种的汇率（美元是对美元的汇率，为1；英镑和欧元是对人民币的汇率，每日维护）
                $orderData['current_currency_value'] = $this->currency->getValue($currency);
                // lilei 修改oc_order表currency_value字段始终为1，该字段涉及account/order展示价格的部分
                $orderData['currency_value'] = 1;
                $orderData['ip'] = $this->request->serverBag->get('REMOTE_ADDR');
                $orderData['forwarded_ip'] = $this->request->serverBag->get('HTTP_X_FORWARDED_FOR') ? $this->request->serverBag->get('HTTP_X_FORWARDED_FOR') : $this->request->serverBag->get('HTTP_CLIENT_IP','');
                $orderData['user_agent'] = $this->request->serverBag->get('HTTP_USER_AGENT');
                $orderData['accept_language'] = $this->request->serverBag->get('HTTP_ACCEPT_LANGUAGE');
                // 云送仓批量导单
                $orderData['cwf_file_upload_id'] = $cwf_file_upload_id;
                //oc_order 和 oc_order_product中新增了transaction_type 以及agreement_id
                $orderId = $this->model_checkout_order->addOrder($orderData);
                // 议价
                app(QuoteService::class)->addOrderQuote($products,$customerId,$orderId,$countryId, customer()->isEurope());
                $this->session->set('order_id', $orderId);
                $this->load->model('tool/image');
                // 用于支付成功后处理,判断是不是buy now
                $this->cache->set('order_id_' . $orderId, $products[0]['cart_id']);
                // 预扣库存
                $this->model_checkout_order->withHoldStock($orderId);
                // 优惠券设置为已使用
                $this->couponService->setCouponUsed($orderId, $couponId, $subItemsAndServiceFeeTotal);
                // 记录订单参与的促销活动
                app(CampaignService::class)->addCampaignOrder(array_merge($campaigns, $totalData['gifts']), $orderId);

                $this->db->commit();
                Logger::order([static::END_ORDER, 'success', 'id' => $orderId]);
                $successFlag = true;
            } catch (Exception $e) {
                $this->db->rollback();
                Logger::order([static::END_ORDER, 'error', 'id' => $order_id ?? 0, 'e' => $e->getMessage()], 'error');
                if ($e->getCode() == 999) {
                    $this->session->set('error_warning_final_cart', 'Agreement product not available in the desired quantity or not in stock! Please contact with our customer service to argue.');
                } else {
                    $this->session->set('error_warning_final_cart', 'The transfer is failed. Please contact with our customer service to argue.');
                }
                $successFlag = false;
                $redirect = $this->url->link('checkout/cart');
            }
        }
        if ($lock->isAcquired()) {
            $lock->release();
        }
        if (isset($checkOrderResult) && !$checkOrderResult) {
            $redirect = $this->url->link('checkout/cart');
        }
        // 订单生成成功
        if ($successFlag) {
            return $this->jsonSuccess(['order_id' => ($orderId ?? 0)], 'Create order successfully');
        }

        if ($this->request->post('cart_id_str')) {
            return $this->jsonFailed('Failed to create order', ['url' => $redirect]);
        } else {
            $error = $this->session->get('error') ?? $this->session->get('error_warning_final_cart');
            $this->session->remove('error');
            $this->session->remove('error_warning_final_cart');
            return $this->jsonFailed($error ?? 'Failed to create order', ['url' => $redirect]);
        }
    }

    /**
     * 创建订单之前验证
     * @param array $products
     * @param int $customerId
     * @param int $deliveryType
     * @param int $cwf_file_upload_id 云送仓批量数据
     * @return bool
     * @throws Exception
     */
    public function validateBeforeCreateOrder($products,$customerId,$deliveryType,$cwf_file_upload_id = 0){
        if (!count($products)){
            return false;
        }
        $setProductArray=[];
        $this->load->model('buyer/buyer_common');
        $this->load->model('futures/agreement');
        $configStockCheckout = $this->config->get('config_stock_checkout');
        $marginAgreementIds = [];
        try {
            foreach ($products as $k => $product) {
                // 验证产品的合法性
                if (!$configStockCheckout && !$this->modelPreOrder->validateProduct($product, $customerId)) {
                    return false;
                }
                // 验证产品的上架库存
                if (!$configStockCheckout && !$this->modelPreOrder->isEnoughProductStock($product['product_id'], $product['quantity'], $product['stock_quantity'], $product['product_type'], $product['agreement_id'], $product['transaction_type'])) {
                    return false;
                }
                // 验证产品最低购买数量
                if ($product['minimum'] > $product['quantity']) {
                    return false;
                }
                // 对常规产品和补差价产品做在库验证
                if ($product['product_type'] == 0 || $product['product_type'] == 3) {
                    if (in_array($product['transaction_type'], [0, 1, 4])) {
                        //非保证金产品，保证金产品库存是在锁定的部分
                        if ($product['combo_flag'] == 1) {
                            $comboInfo = $this->model_buyer_buyer_common->getComboInfoByProductId($product['product_id'], $product['quantity']);
                            $setProductArray = array_merge($setProductArray, $comboInfo);
                        } else {
                            $productInfo = array();
                            $productInfo[] = array(
                                'set_product_id' => $product['product_id'],
                                'qty'            => $product['quantity']
                            );
                            $setProductArray = array_merge($setProductArray, $productInfo);
                        }
                    }
                }
                // 判断是不是期货头款
                if ($product['product_type'] == $this->modelPreOrder::PRODUCT_FUTURE) {
                    $this->load->language('checkout/cwf_info');
                    //期货二期，判断是否有足够的期货合约可用数量
                    if (!$this->model_futures_agreement->isEnoughContractQty($product['agreement_id'])) {
                        $this->session->set('error', sprintf($this->language->get('check_contract_error_quantity'), $this->model_futures_agreement->getAgreementNoByAgreementId($product['agreement_id'])));
                        return false;
                    }
                    //期货二期，判断是否有足够的合约保证金
                    $contractRes = $this->model_futures_agreement->isEnoughContractMargin($product['agreement_id']);
                    if (!$contractRes['status']) {
                        $this->session->set('error', sprintf($this->language->get('error_futures_low_deposit'), $contractRes['agreement_no']));
                        return false;
                    }
                }
                // 判断是不是现货保证金头款且不是期货转现货的
                if ($product['product_type'] == $this->modelPreOrder::PRODUCT_MARGIN && !app(MarginRepository::class)->checkMarginIsFuture2Margin($product['agreement_id'])) {
                    // 需要验证上架数量以及在库数量
                    $agreementDetail = app(MarginRepository::class)->getMarginAgreementInfo($product['agreement_id']);
                    $agreementProductAvailableQty = $this->model_common_product->getProductAvailableQuantity((int)$agreementDetail['product_id']);
                    if (
                        ($agreementDetail['num'] > $agreementProductAvailableQty) // 校验在库
                        || ($agreementDetail['num'] > $agreementDetail['available_qty']) // 校验上架
                    ) {
                        $this->session->set('error', "Product not available in the desired quantity or not in stock! Please contact with our customer service to argue. ");
                        return false;
                    }
                }

                // 现货保证金头款支付 && 商品属于Onsite Seller 需要校验 Onsite Seller 应收款是否足够
                if ($product['product_type'] == ProductType::MARGIN_DEPOSIT && $product['accounting_type'] == CustomerAccountingType::GIGA_ONSIDE) {
                    $marginAgreementIds[] = $product['agreement_id'];
                }
            }

            // 对常规产品和补差价产品做在库验证
            $resolve_order_qty = [];
            foreach ($setProductArray as $setProduct) {
                $resolve_order_qty[] = ['product_id' => $setProduct['set_product_id'], 'quantity' => $setProduct['qty']];
            }
            // 对常规产品和补差价产品做在库验证
            if (!$this->model_common_product->checkProductQuantityValid($resolve_order_qty)) {
                $this->session->set('error', "Product not available in the desired quantity or not in stock! Please contact with our customer service to argue. ");
                return false;
            }
            // 如果是云送仓的订单校验，购物车与填写的云送仓文件是否匹配
            if ($deliveryType == 2) {
                // 云送仓批量导单不存在购物车，不需要再次验证数据，只需要校验库存即可
                if(!$cwf_file_upload_id){
                    $cloudFlag = $this->modelPreOrder->checkCloudLogisticsOrder($products);
                    if (!$cloudFlag) {
                        $this->session->set('error', 'Items in your cart has been changed, please refresh the page and submit again.');
                        return false;
                    }
                }
            }

            // 存在Onsite Seller的现货头款保证金商品，检测Onsite Seller的账户余额是否充足(需要排序由期货转现货头款)
            if ($marginAgreementIds) {
                $notBuyList = app(MarginRepository::class)->checkOnsiteSellerAmountByAgreementIds($marginAgreementIds, $this->customer->getCountryId());
                if ($notBuyList) {
                    $this->session->set('error', sprintf("There are risks in this seller's account, and the Margin Agreement (ID: %s) is unable to be purchased", implode(',', $notBuyList)));
                    return false;
                }
            }
        } catch (Exception $e) {
            return false;
        }
        return true;
    }

    /**
     * @deprecated 已经转移到QuoteService::addOrderQuote
     */
    public function addOrderQuote($product, $customerId,$orderId,$countryId)
    {
        $currency = $this->session->get('currency');
        $this->load->model('account/product_quotes/wk_product_quotes');
        $isEuropean = $this->isEuropeanAccount();
        foreach ($product as $item) {
            if ($item['transaction_type'] == $this->modelPreOrder::TRANSACTION_SPOT) {
                $item = $this->modelPreOrder->calculateSpotDiscountAmount($item, $countryId, $isEuropean, $currency);
                $amount_data = [
                    'isEuropean'             => $isEuropean,
                    'amount_price_per'       => $item['quote_discount_amount_per'],// 每件商品的折扣
                    'amount_total' => bcmul(($item['quote_discount_amount_per'] + $item['quote_discount_service_per']), $item['quantity'], 2),   //议价总折扣
                    'amount_service_fee_per' => $item['quote_discount_service_per'],
                    'amount_service_fee'     => bcmul($item['quote_discount_service_per'], $item['quantity'], 2) // // 如果为欧洲地区，则为商品服务费的折扣金额；如果非欧洲地区该值为0
                ];
                $this->model_account_product_quotes_wk_product_quotes->addOrderQuote(
                    $orderId,
                    $item['agreement_id'],
                    $customerId,
                    $amount_data,
                    $item['product_id']
                );
            }
        }
    }

    /**
     * 判断云送仓的购物车与填写的云送仓信息是否匹配
     * @param array $cartId 购物车ID数组
     *
     * @return bool
     * @throws Exception
     * @author xxl
     */
    private function checkCloudLogisticsOrder(array $cartId = []){
        //没有找到云送仓填写的订单信息
        if(!empty($this->session->get('cwf_id'))) {
            //查看云送仓的填写的信息
            $this->load->model('checkout/cwf_info');
            $this->model = $this->model_checkout_cwf_info;
            $cloudItems = $this->model->getCloudLogisticsItems($this->session->get('cwf_id'));
            $cartProducts = $this->cart->getProducts(null, $this->session->get('delivery_type'),$cartId);
            $diff_arr = array_udiff($cloudItems,$cartProducts,"compare_array");
            if(isset($diff_arr) && count($diff_arr)>0){
                return false;
            }else{
                return true;
            }
        }else {
            return false;
        }
    }

    // 支付中专页，用于保存并且隐藏url中的支付信息
    public function toPay()
    {
        $productOrderId = $this->request->get('order_id', 0);
        $feeOrderIdStr = $this->request->get('fee_order_list', '');
        $feeOrderIdArr = array_filter(array_unique(explode(',', $feeOrderIdStr)));
        $data = [
            'order_id' => $productOrderId,
            'fee_order_id' => $feeOrderIdArr,
            'order_source' => $this->request->get('order_source', 'purchase'), // 判断支付订单的类型来源(采购，销售)(purchase, sale)
        ];
        if (request()->attributes->has('is_virtual_pay')) {
            $data['is_virtual_pay'] = request()->attributes->get('is_virtual_pay');
        }

        $payNumber = Carbon::now()->format('ymdHis') . random_int(100000, 999999) . customer()->getUserNumber();
        $expireTime = configDB('expire_time', 30) + 10;// 获取支付有效期，默认加十分钟
        cache()->set($payNumber, $data, $expireTime * 60);
        return $this->redirect(url(['checkout/confirm/pay', 'pay_number' => $payNumber]));
    }

    // 支付页
    public function pay()
    {
        $payNumber = $this->request->get('pay_number');
        $payData = cache($payNumber);
        if (!$payData) {
            // 数据不存在，number错误，数据过期等都会走到这里面
            throw new NotFoundException('Order Error!');
        }

        $data['order_source'] = $payData['order_source'] ?? 'purchase';
        //商品单订单id
        $productOrderId = $payData['order_id'] ?? 0;
        $data['order_id'] = $productOrderId;
        //费用单订单id
        $feeOrderIdArr = $payData['fee_order_id'] ?? [];
        $data['fee_order_id'] = json_encode($feeOrderIdArr);
        // 加锁防止有订单正在处理modifiedOrder
        $lockKey = $productOrderId . join(',', $feeOrderIdArr);
        $lock = Locker::toPay($lockKey, 1);
        if ($lock->acquire(true)) {
            $this->load->language('checkout/checkout');
            $this->load->model('setting/extension');
            $this->load->model('account/customer');
            $this->load->model('checkout/order');
            $this->load->model('checkout/pay');
            $this->load->model('account/customer_order');

            //商品单的订单信息
            $orderInfo = $this->model_checkout_order->orderBaseInfo($productOrderId);
            //费用单的订单信息
            $feedOrderInfos = app(FeeOrderRepository::class)->findFeeOrderInfo($feeOrderIdArr);

            // 切换账号 数据异常处理
            $changeAccountFlag = false;
            if ($productOrderId != 0 && empty($orderInfo)) {
                $changeAccountFlag = true;
            }
            if ($feedOrderInfos) {
                $buyIds = array_unique(array_column($feedOrderInfos, 'buyer_id'));
                // 数据有异常 跳转到销售订单
                if (empty($buyIds) || count($buyIds) != 1 || $buyIds[0] != customer()->getId()) {
                    $changeAccountFlag = true;
                }
            }
            if ($changeAccountFlag) {
                $salesOrderUrl = customer()->isCollectionFromDomicile() ? url('account/customer_order')
                    : url('account/sales_order/sales_order_management');
                return $this->response->redirectTo($salesOrderUrl);
            }

            //获取订单失效时间,由于费用单和商品单同时生成
            //如果存在$productOrderId
            if ($productOrderId != 0) {
                PayCode::setPoundageCalculateTime($orderInfo['date_added']);
                $intervalTime = $this->model_checkout_order->checkOrderExpire($productOrderId);
                if (isset($orderInfo['order_status_id']) && OcOrderStatus::COMPLETED == $orderInfo['order_status_id']) {
                    return $this->response->redirectTo($this->url->link('checkout/success', '&o=' . $productOrderId));
                } elseif (empty($orderInfo) || OcOrderStatus::TO_BE_PAID != $orderInfo['order_status_id'] || $intervalTime > $this->config->get('expire_time')) {
                    return $this->response->redirectTo($this->url->link('checkout/cart'));
                }
            }
            if (!empty($feedOrderInfos)) {
                PayCode::setPoundageCalculateTime($feedOrderInfos[0]['created_at']);
                //获取订单失效时间
                $date_add = $feedOrderInfos[0]['created_at'];
                $feeOrderIntervalTime = (time() - strtotime($date_add)) / 60;
                if (isset($feedOrderInfos[0]['status']) && 5 == $feedOrderInfos[0]['status']) {
                    return $this->response->redirectTo($this->url->link('checkout/success', '&o=' . $productOrderId));
                } elseif (0 != $feedOrderInfos[0]['status'] || $feeOrderIntervalTime > $this->config->get('expire_time')) {
                    return $this->response->redirectTo($this->url->link('checkout/cart'));
                }
            }
            //清除上次的填写的数据
            $this->model_checkout_pay->deleteOrderPaymentInfo($productOrderId);
            $this->model_checkout_pay->deleteFeeOrderPaymentInfo($feeOrderIdArr);
            $purchaseOrderTotal = $this->model_checkout_pay->getOrderTotal($productOrderId);
            $feeOrderTotal = app(FeeOrderRepository::class)->findFeeOrderTotal($feeOrderIdArr);
            $data['total'] = $purchaseOrderTotal + $feeOrderTotal;
            $data['total_currency'] = $this->currency->format($data['total'], $this->session->get('currency'));
            // Payment Methods
            $method_data = [];
            $isUSBuyer = $this->model_account_customer->isUSBuyer();
            foreach (PayCode::getSupportedPayCodes() as $payCode) {
                //美国用户不展示umf_pay和wechat_pay
                // 38226 没有销售单的购买不展示
                if (($isUSBuyer || $data['order_source'] != 'sale') && in_array($payCode, [PayCode::PAY_WECHAT, PayCode::PAY_UMF])) {
                    continue;
                }
                $method_data[$payCode] = [
                    'code' => $payCode,
                    'title' => PayCode::getDescriptionWithPoundage($payCode),
                    'poundage' => PayCode::getPoundage($payCode),
                ];
            }

            $data['payment_methods'] = $method_data;

            if (empty($data['payment_methods'])) {
                $data['error_warning'] = sprintf($this->language->get('error_no_payment'), $this->url->link('information/contact'));
            } else {
                $data['error_warning'] = '';
            }

            $tmp = current($data['payment_methods']);
            $data['code'] = $tmp['code'];
            $data['comment'] = $this->session->get('comment', '');
            $data['agree'] = $this->session->get('agree', '');

            $data['scripts'] = $this->document->getScripts();

            if ($this->config->get('config_checkout_id')) {
                $this->load->model('catalog/information');
                $information_info = $this->model_catalog_information->getInformation($this->config->get('config_checkout_id'));
                if ($information_info) {
                    $data['text_agree'] = sprintf($this->language->get('text_agree'), $this->url->link('information/information/agree', 'information_id=' . $this->config->get('config_checkout_id'), true), $information_info['title'], $information_info['title']);
                }
            } else {
                $data['text_agree'] = '';
            }

            $data['balance'] = $this->customer->getLineOfCredit();
            $data['balance_currency'] = $this->currency->format($data['balance'], $this->session->get('currency'));
            if ($this->session->get('wechatInfo')) {
                $data['wechatInfo'] = $this->session->get('wechatInfo');
            }
            if (isset($data['payment_methods'][PayCode::PAY_LINE_OF_CREDIT])) {
                $data['balance_title'] = $data['payment_methods'][PayCode::PAY_LINE_OF_CREDIT]['title'];
            } else {
                $data['balance_title'] = PayCode::getDescription(PayCode::PAY_LINE_OF_CREDIT);
            }
            //订单超时时间
            $data['expire_time_notice'] = sprintf($this->language->get('expire_time_notice'), $this->config->get('expire_time'));
            $orderAddTime = 0;
            if ($productOrderId != 0) {
                $orderAddTime = $this->model_checkout_pay->getOrderAddTime($productOrderId);
            }
            if (!empty($feeOrderIdArr)) {
                $orderAddTime = $feedOrderInfos[0]['created_at'];
            }
            $leftSec = strtotime($orderAddTime) + ($this->config->get('expire_time') * 60) - time();
            $i = ($leftSec / 60) % 60;
            $s = $leftSec % 60;
            $data['expire_time'] = sprintf('%02d:%02d', $i, $s);
            $data['expire_time_num'] = $leftSec;

            //3150 需求变更是否需要二级密码取配置值
            $data['checkSecondPassword'] = intval($this->customer->getCustomerExt(3));

            // 能否使用虚拟支付,过滤立即采购合并
            // 产品详情页面的buy now可以选择直接货值采购
            if ($this->customer->innerAutoBuyAttr1() && empty($productOrderId)) {
                //内部自动购买单存支付费用单
                $data['virtual_pay'] = true;
            } else {
                $data['virtual_pay'] = $this->model_checkout_pay->canVirtualPay($productOrderId);
            }
            // 产品详情页面的buy now可以选择直接货值采购
            if (isset($payData['is_virtual_pay']) && $payData['is_virtual_pay'] == 0) {
                $data['virtual_pay'] = 0;
            }

            $data['virtual_pay_check'] = $data['virtual_pay'] ? 1 : 0;
            $data['addition_flag'] = $this->customer->getAdditionalFlag() == 1 ? $this->customer->getAdditionalFlag() : 0;

            $data['country_id'] = $this->customer->getCountryId();

            $data['breadcrumbs'] = [
                [
                    'text' => $this->language->get('text_home'),
                    'href' => $this->url->link('common/home')
                ],
                [
                    'text' => $this->language->get('text_cart'),
                    'href' => $this->url->link('checkout/cart')
                ],
                [
                    'text' => $this->language->get('heading_title'),
                    'href' => url()->to(['checkout/confirm/toPay', 'order_id' => $productOrderId, 'order_source' => $data['order_source']]),
                ]
            ];
            list($data['asset_control'], $assetControlSeller) = app(OrderRepository::class)->checkAssetControlByOrder($productOrderId);
            if (!$data['asset_control']) {
                $data['asset_control_screen_name_str'] = app(SellerRepository::class)->getSellerInfo($assetControlSeller)->implode('screenname', ',');
            }
            $this->document->setTitle($this->language->get('heading_title'));
            $data['cybersource_sop_form'] = $this->load->controller('extension/payment/cybersource_sop/cybersourceForm', ['order_id' => $productOrderId, 'fee_order_id' => $feeOrderIdArr]);
            $data['success'] = $this->url->link('checkout/success', '&o=' . $productOrderId . '&f=' . implode(',', $feeOrderIdArr));
            $data['column_left'] = $this->load->controller('common/column_left');
            $data['column_right'] = $this->load->controller('common/column_right');
            $data['content_top'] = $this->load->controller('common/content_top');
            $data['content_bottom'] = $this->load->controller('common/content_bottom');
            $data['footer'] = $this->load->controller('common/footer');
            $data['header'] = $this->load->controller('common/header');
            $data['country_id'] = $this->customer->getCountryId();
            $data['decimal'] = $this->customer->getCountryId() == JAPAN_COUNTRY_ID ? 0 : 2;
            $data['symbolLeft'] = $this->currency->getSymbolLeft($this->session->get('currency'));
            $data['symbolRight'] = $this->currency->getSymbolRight($this->session->get('currency'));
            $data['request_interval_seconds'] = config('request_interval_seconds', 10);
            //解锁
            $lock->release();
            $this->response->setOutput($this->load->view('checkout/to_pay', $data));
        }
    }

    public function createPoundage(){
        $this->load->language('checkout/checkout');
        $this->load->model('account/customer');
        $this->load->model('checkout/order');
        $this->load->model('checkout/pay');
        $balance = $this->request->post('balance', 0);
        $payment_method = $this->request->post('payment_method', 0);
        $order_id = $this->request->post('order_id', 0);

        $orderInfo = $this->model_checkout_order->orderBaseInfo($order_id);
        if ($order_id && !empty($orderInfo)) {
            PayCode::setPoundageCalculateTime($orderInfo['date_added']);
        }
        //获取订单失效时间
        $intervalTime = $this->model_checkout_order->checkOrderExpire($order_id);
        if (empty($orderInfo) || OcOrderStatus::TO_BE_PAID != $orderInfo['order_status_id'] || $intervalTime > $this->config->get('expire_time')) {
            return $this->response->redirectTo($this->url->link('checkout/cart'));
        }
        //清除上次的填写的数据
        $this->model_checkout_pay->deleteOrderPaymentInfo($order_id);
        $order_total = $this->model_checkout_pay->getOrderTotal($order_id);
        $data = array(
            'balance' => $balance,
            'payment_method' => $payment_method,
            'order_total' => $order_total
        );
        $poundage = $this->getPoundage($data);
        $poundage_currency = $this->currency->format($poundage, session('currency'));
        //订单总额
        $grand_total = $order_total+$poundage;
        $grand_total_currency = $this->currency->format($grand_total, session('currency'));
        $json['grand_total_currency'] = $grand_total_currency;
        $json['poundage_currency'] = $poundage_currency;
        $json['poundage'] = $poundage;
        $payment_method_total = $order_total - $balance;
        $payment_method_total_currency = $this->currency->format($payment_method_total>0?$payment_method_total:0, session('currency'));
        $json['payment_method_total_currency'] = $payment_method_total_currency;
        $this->response->setOutput(json_encode($json));
    }

    //点击continue
    public function modifiedOrder(){
        $this->load->language('checkout/checkout');
        $this->load->model('account/customer');
        $this->load->model('checkout/order');
        $this->load->model('checkout/pay');
        $balance = $this->request->post('balance', 0);
        $payment_code = $this->request->post('payment_method', PayCode::PAY_LINE_OF_CREDIT);
        $productOrderId = $this->request->post('order_id', 0);
        $virtual_pay = $this->request->post('virtual_pay', 0);
        $comment = $this->request->post('comment', '');
        $feeOrderIdArr = $this->request->post('fee_order_id', []);
        $orderSource = $this->request->post('order_source', []);
        if($payment_code === 'cybersource_sop'){
            $feeOrderIdArr= ($feeOrderIdArr == '')?[]:explode(',',$feeOrderIdArr);
        }

        // 加锁处理防止多次处理订单
        $lockKey = $productOrderId . join(',', $feeOrderIdArr);
        $lock = Locker::toPay($lockKey);
        if (!$lock->acquire()) {
            $json['order_status'] = false;
            $json['redirect'] = url()->to(['checkout/confirm/toPay', 'order_id' => $productOrderId, 'order_source' => $orderSource]);
            $json['payment_method'] = $payment_code;
            goto end;
        }

        $orderInfo = $this->model_checkout_order->orderBaseInfo($productOrderId);
        if ($orderInfo && isset($orderInfo['date_added'])) {
            PayCode::setPoundageCalculateTime($orderInfo['date_added']);
        }
        $feeOrderRepo = app(FeeOrderRepository::class);
        $feedOrderInfos = $feeOrderRepo->findFeeOrderInfo($feeOrderIdArr);
        $json['order_status'] = true;

        // 校验是否切换了账号点击continute
        $changeAccountFlag = false;
        if ($productOrderId != 0 && empty($orderInfo)) {
            $changeAccountFlag = true;
        }
        if ($feedOrderInfos) {
            $buyIds = array_unique(array_column($feedOrderInfos, 'buyer_id'));
            if (empty($buyIds) || count($buyIds) != 1 || $buyIds[0] != customer()->getId()) {
                $changeAccountFlag = true;
            }
        }
        if ($changeAccountFlag) {
            // 数据有异常 跳转到销售订单
            $salesOrderUrl = customer()->isCollectionFromDomicile() ? url('account/customer_order')
                : url('account/sales_order/sales_order_management');
            $json['order_status'] = false;
            $json['redirect'] = $salesOrderUrl;
            goto end;
        }

        //获取订单失效时间
        if($productOrderId != 0){
            $intervalTime = $this->model_checkout_order->checkOrderExpire($productOrderId);
            if (empty($orderInfo) || FeeOrderStatus::WAIT_PAY != $orderInfo['order_status_id'] || $intervalTime > $this->config->get('expire_time')) {
                $json['order_status'] = false;
                $json['redirect'] = $this->url->link('checkout/cart');
            }
        }
        if(!empty($feedOrderInfos)){
            $date_add = $feedOrderInfos[0]['created_at'];
            PayCode::setPoundageCalculateTime($date_add);
            $intervalTime = (time() - strtotime($date_add)) / 60;
            if ( FeeOrderStatus::WAIT_PAY != $feedOrderInfos[0]['status'] || $intervalTime > $this->config->get('expire_time')) {
                $json['order_status'] = false;
                $json['redirect'] = $this->url->link('checkout/cart');
            }
            foreach ($feedOrderInfos as $feedOrderInfo) {
                if ($feedOrderInfo['fee_total'] > 0) {
                    $canPay = app(FeeOrderRepository::class)->isFeeOrderNeedPay(FeeOrder::find($feedOrderInfo['id']));
                    if (!$canPay) {
                        $json['order_status'] = false;
                        $salesOrderId = CustomerSalesOrder::where('id', $feedOrderInfo['order_id'])->value('order_id');
                        $json['redirect'] = url(['account/order', 'filter_fee_order_no' => $salesOrderId, '#' => 'tab_fee_order']);
                        $json['msg'] = 'Information shown on this screen has been updated.';
                        break;
                    }
                }
            }
        }
        // 风控校验
        list($assetControl, $assetControlSeller) = app(OrderRepository::class)->checkAssetControlByOrder($productOrderId);
        if (!$assetControl) {
            $json['order_status'] = false;
            $json['redirect'] = url()->to(['checkout/confirm/toPay', 'order_id' => $productOrderId, 'order_source' => $orderSource]);
            $screenName = app(SellerRepository::class)->getSellerInfo($assetControlSeller)->implode('screenname', ',');
            $json['msg'] = __('触发风控提醒', ['screenName' => $screenName], 'controller/seller_asset');
        }

        // Onsite Seller 现货头款，账户金额校验,是否充足(需要排除期货转现货头款)
        $notBuyList = app(MarginRepository::class)->checkOnsiteSellerAmountByOrderId($productOrderId, $this->customer->getCountryId());
        if ($notBuyList) { // 存在不能购买现货的协议
            $json['order_status'] = false;
            $json['redirect'] = $this->url->link('checkout/cart');
            $json['msg'] = $this->language->get('error_onsite_seller_active_amount');
        }

        if($json['order_status']) {
            //清除上次的填写的数据
            $this->model_checkout_pay->deleteOrderPaymentInfo($productOrderId);
            $this->model_checkout_pay->deleteFeeOrderPaymentInfo($feeOrderIdArr);
            //校验金额
            $purchaseOrderTotal = $this->model_checkout_pay->getOrderTotal($productOrderId);
            $feeOrderTotal = $feeOrderRepo->findFeeOrderTotal($feeOrderIdArr);
            $orderTotal = $purchaseOrderTotal+$feeOrderTotal;
            if($payment_code == PayCode::PAY_LINE_OF_CREDIT && $balance == 0 && $virtual_pay != self::CAN_VIRTUAL){
                $balance = $orderTotal;
            }

            // #28383 非常规修改balance，导致订单完成，但金额不正确（如：在使用全部信用额度时，修改请求中的balance金额为0.01，则订单也会是完成状态，实际支付金额为）
            if($payment_code == PayCode::PAY_LINE_OF_CREDIT && bccomp($balance, $orderTotal, 2) != 0 && $virtual_pay != self::CAN_VIRTUAL) {
                $json['order_status'] = false;
                $json['msg'] = 'The current payment amount is incorrect. Please check and try again later.';
                $redirectUrl = ['checkout/confirm/toPay', 'order_id' => $productOrderId, 'order_source' => $orderSource];
                if (!empty($feeOrderIdArr)) {
                    $redirectUrl['fee_order_list'] = join(',', $feeOrderIdArr);
                }
                $json['redirect'] = url($redirectUrl);
                $json['payment_method'] = $payment_code;
                $json['status'] = 0;
                goto end;
            }
            if($balance>=$orderTotal){
                $balance = $orderTotal;
                $payment_code = PayCode::PAY_LINE_OF_CREDIT;
            }
            //获取总手续费
            $totalPoundage = $this->getPoundage(['balance'=>$balance,'payment_method'=>$payment_code,'order_total'=>$orderTotal]);
            //订单支付数据
            $payment_code = $virtual_pay == self::CAN_VIRTUAL ? PayCode::PAY_VIRTUAL : $payment_code;
            $payData = [
                'order_id' => $productOrderId,
                'fee_order_id' => $feeOrderIdArr,
                'balance' => $balance,
                'payment_code' => $payment_code,
                'totalPoundage' => $totalPoundage,
                'comment'=>$comment
            ];

            //修改商品订单信息，更新信用额度使用,已经手续费
            if(!empty($productOrderId)) {
                $payData = $this->model_checkout_pay->modifiedOrder($payData);
            }
            //费用订单信息，更新信用额度使用,已经手续费
            if(!empty($feeOrderIdArr)){
                $this->model_checkout_pay->modifiedFeeOrder($payData);
            }

            $payData['balance'] = $balance;
            //校验支付方式
            if($virtual_pay == self::CAN_VIRTUAL){
                $can_virtual_pay = false;
                if ($this->customer->innerAutoBuyAttr1() && empty($productOrderId)) {
                    $can_virtual_pay = true;
                }
                if(!empty($productOrderId)){
                    // 能否使用虚拟支付
                    $can_virtual_pay = $this->model_checkout_pay->canVirtualPay($productOrderId);
                }
                if(!$can_virtual_pay){
                    $json['redirect'] = url()->to(['checkout/confirm/toPay', 'order_id' => $productOrderId, 'order_source' => $orderSource]);
                    $json['payment_method'] = 'error';
                    $json['msg'] = 'Virtual payment is not available for this order. Please select an alternative payment method.';
                }else {
                    //虚拟支付
                    $json = $this->load->controller('extension/payment/virtual_pay/confirm', $payData);
                    $json['payment_method'] = PayCode::PAY_VIRTUAL;
                }
            }else {
                //如果未使用umf_pay,wechat_pay,cybersource_sop直接修改订单状态
                if ($payment_code === PayCode::PAY_LINE_OF_CREDIT) {
                    $json = $this->load->controller('extension/payment/line_of_credit/confirmNew',$payData);
                } elseif ($payment_code === PayCode::PAY_UMF) {
                    $json = $this->load->controller('extension/payment/umf_pay/confirmNew',$payData);
                } elseif ($payment_code === PayCode::PAY_CREDIT_CARD) {
                    $json = $this->load->controller('extension/payment/cybersource_sop/createOrder', $payData);
                }
                $json['payment_method'] = $payment_code;
                $json['order_status'] = true;
            }
        }

        end:
        $lock->release();

        $json['order_id'] = $productOrderId;
        $this->response->setOutput(json_encode($json));
    }

    /**
     * 获取信用额度
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function getLineOfCredit()
    {
        $price = $this->customer->getLineOfCredit();
        return $this->jsonSuccess([
            'price' => $price,
            'format_price' => $this->currency->format($price, $this->session->get('currency')),
        ]);
    }

    /**
     * 获取订单服务费
     * @param $data
     * @return float|int
     */
    public function getPoundage($data){
        $balance = $data['balance'];
        $payment_method = $data['payment_method'];
        $order_total =  $data['order_total'];
        //扣除使用余额的剩余订单金额
        $payment_method_total = (float)$order_total - (float)$balance;
        $additionalFlag = $this->customer->getAdditionalFlag();
        $balance_poundage = 0;
        $payment_poundage = 0;

        //使用余额的手续费
        if($additionalFlag ==  1){
            $balance_poundage = $this->customer->getCountryId()== 107 ? round($balance*0.01,0):round($balance*0.01,2);
        }
        // 其他支付方式的手续费
        $poundagePercent = PayCode::getPoundage($payment_method);
        if ($poundagePercent > 0) {
            $payment_poundage = $this->customer->getCountryId()== 107 ? round($payment_method_total*$poundagePercent,0):round($payment_method_total*$poundagePercent,2);
        }

        //总服务费
        $poundage = $balance_poundage+$payment_poundage;
        return $poundage;
    }

    //根据下单页生成订单
    public function createOrderForPurchaseList()
    {
        //判断是否为欧洲
        $data['isEuropean'] = false;
        if (!empty($this->customer->getCountryId()) && $this->country->isEuropeCountry($this->customer->getCountryId())) {
            $data['isEuropean'] = true;
        }
        $this->session->remove('shipping_address');
        $this->session->remove('shipping_method');
        $this->session->remove('shipping_methods');
        $run_id = $this->request->get( 'run_id', '0');
        $couponId = $this->request->post('coupon_id');
        $cartIdStr = $this->request->post( 'cart_id_str', '');
        $deliveryType = $this->request->post('delivery_type', 0);
        $totalStorageFee = $this->request->post('total_storage_fee');
        $safeguards = $this->request->post('safeguards');
        $preSubItemTotal = $this->request->post('sub_item_total', 0);
        session()->set('delivery_type', $deliveryType);
        $cartIdArr = explode(',', $cartIdStr);
        $customer_id = $this->customer->getId();
        $countryId = customer()->getCountryId();
        // Validate minimum quantity requirements.
        $originalProducts = $this->modelPreOrder->getPreOrderCache($cartIdStr);
        $products = $this->modelPreOrder
            ->handleProductsData($originalProducts, $customer_id, $deliveryType, customer()->isCollectionFromDomicile(), $countryId);
        $verifyProducts = $this->cart->getProducts(null, $deliveryType, $cartIdArr);
        $redirect = '';
        $precision = $this->customer->getCountryId() == JAPAN_COUNTRY_ID ? 0 : 2;
        $msg = 'Create Order Error';
        // Validate cart has products and has stock.
        if (!count($products) && empty($this->session->data['vouchers'])){
            $redirect = $this->url->link('checkout/cart');
        }

        foreach ($verifyProducts as $k => $product) {
            if (!$this->config->get('config_stock_checkout') && !$product['stock']){
                $msg = $product['sku']." not available in the desired quantity or not in stock! Please contact with our customer service to argue.";
                $redirect = $this->url->link('checkout/cart');
                break;
            }

            if ($product['minimum'] > $product['quantity']) {
                $msg = $product['sku']." not available in the desired quantity or not in stock! Please contact with our customer service to argue.";
                $redirect = $this->url->link('checkout/cart');
                break;
            }
        }

        //子sku相同的产品库存检验;N-475
        $this->load->model('buyer/buyer_common');
        foreach ($products as $product) {
            $resolve_order_qty[] = ['product_id' => $product['product_id'], 'quantity' => $product['quantity']];
        }
        if (!$this->model_common_product->checkProductQuantityValid($products)) {
            $msg = "Product not available in the desired quantity or not in stock! Please contact with our customer service to argue. ";
            $redirect = $this->url->link('account/sales_order/sales_order_management/salesOrderPurchaseOrderManagement&run_id='.$run_id);
        }

        if (!$redirect) {

            try {
                $this->db->beginTransaction();
                $this->load->model('setting/extension');
                $this->load->model('account/customer');
                $this->load->model('checkout/marketing');
                $this->load->model('checkout/order');
                $this->load->model('tool/upload');
                $this->load->model('catalog/product');
                $this->load->model('account/customerpartner');
                $this->load->model('tool/image');
                $this->load->model('account/sales_order/match_inventory_window');
                $this->load->model('account/cart/cart');
                $this->load->language('checkout/checkout');
                $this->load->language('customerpartner/profile');
                $totalData = $this->model_account_cart_cart
                    ->orderTotalShow($products, true, ['coupon_ids' => (empty($couponId) ? [] : [$couponId]), 'limit_quote' => false]);
                $customer_info = $this->model_account_customer->getCustomer($customer_id);

                $order_data = [
                    'totals'  => $totalData['all_totals'],
                    'invoice_prefix'    => $this->config->get('config_invoice_prefix'),
                    'store_id'  => $this->config->get('config_store_id'),
                    'store_name'=> $this->config->get('config_name'),
                    'customer_id'   => $customer_id,
                    'customer_group_id' => $customer_info['customer_group_id'],
                    'firstname' => $customer_info['firstname'],
                    'lastname'  => $customer_info['lastname'],
                    'email'     => $customer_info['email'],
                    'telephone' => $customer_info['email'],
                    'custom_field'  => json_decode($customer_info['custom_field'], true),
                ];

                if ($order_data['store_id']) {
                    $order_data['store_url'] = $this->config->get('config_url');
                } else {
                    if ($this->request->server['HTTPS']) {
                        $order_data['store_url'] = HTTPS_SERVER;
                    } else {
                        $order_data['store_url'] = HTTP_SERVER;
                    }
                }
                $collection = collect($totalData['totals'])->keyBy('code');
                $campaigns = $collection['promotion_discount']['discounts'] ?? []; // 促销活动
                $subItemsTotal = $collection['sub_total']['value'] + ($collection['wk_pro_quote']['value'] ?? 0); //货值金额
                $serviceFeeTotal = $collection['service_fee']['value'] ?? 0; // 总服务费
                $subItemsAndServiceFeeTotal = bcadd($subItemsTotal, $serviceFeeTotal, 4); //总货值金额+总服务费
                // 计算每个产品占用满减份额
                $campaignPerDiscount = app(CampaignService::class)->calculateMutiCampaignDiscount($campaigns, $products, $precision);
                // 判断是否使用优惠券
                if ($couponId) {
                    $coupon = Coupon::find($couponId);
                    // 计算每个产品占用优惠券份额
                    $couponPerDiscount = $this->couponService->calculateCouponDiscount($coupon->denomination, $products, $precision);
                }
                // 阶梯价下单页面会出现和支付金额不一致,暂时去掉此代码
                // 判断下单页面货值金额和此时货值金额是否一致
                if (bccomp($subItemsTotal, $preSubItemTotal, 2) !== 0) {
                    throw new Exception('The current price is invalid. Please refresh the page and try again.');
                }
                $order_data['products'] = array();

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
//                    if ($product['product_type'] == ProductType::NORMAL && $product['discount_info'] instanceof MarketingTimeLimitProduct && $product['discount_info']->qty < $product['quantity']) {
//                        return $this->jsonFailed(' Product not available in the desired quantity or not in stock!', [], 405);
//                    }
                    //$serviceFeePer = $product['discountPrice'] - round($product['price'], 2);
                    $order_data['products'][] = array(
                        'product_id' => $product['product_id'],
                        'name' => $product['name'],
                        'model' => $product['model'],
                        'option' => [],
                        'quantity' => $product['quantity'],
                        'subtract' => $product['subtract'],
                        'price' => round($product['product_price_per'], 2),//使用货值，即上门取货价
                        'serviceFeePer' => round($product['service_fee_per'], 2),
                        'serviceFee' => round($product['service_fee_per'], 2) * $product['quantity'],
                        'total' => round($product['current_price'], 2) * $product['quantity'],
                        'tax' => 0,
                        'freight_per' => $product['freight_per'],//单件运费
                        'base_freight' => $product['base_freight'],//基础运费
                        'overweight_surcharge' => $product['overweight_surcharge'],//超重附加费
                        'package_fee' => $product['package_fee_per'], //打包费
                        'coupon_amount' => $couponPerDiscount[$product['product_id']]['discount'] ?? 0, //优惠券金额
                        'campaign_amount' => $campaignPerDiscount[$product['product_id']]['discount'] ?? 0, //满减金额
                        'discount' => $discount, //产品折扣
                        'discount_price' => $product['discount_price'] ?? 0, //产品折扣
                        'discount_info' => $product['discount_info'],
                        'cart_id' => $product['cart_id'],
                        'type_id' => $product['type_id'],
                        'agreement_id' => $product['agreement_id'],
                        'agreement_code' => $product['agreement_code'],
                        'volume' => $product['volume'],
                        'volume_inch'=> $product['volume_inch'],
                        'danger_flag'=> $product['danger_flag'] ?? 0,
                    );
                }

                // Gift Voucher
                $order_data['vouchers'] = array();

                if (!empty($this->session->data['vouchers'])) {
                    foreach (session('vouchers', []) as $voucher) {
                        $order_data['vouchers'][] = array(
                            'description' => $voucher['description'],
                            'code' => token(10),
                            'to_name' => $voucher['to_name'],
                            'to_email' => $voucher['to_email'],
                            'from_name' => $voucher['from_name'],
                            'from_email' => $voucher['from_email'],
                            'voucher_theme_id' => $voucher['voucher_theme_id'],
                            'message' => $voucher['message'],
                            'amount' => $voucher['amount']
                        );
                    }
                }

                $order_data['total'] = $totalData['total'];

                if (isset($this->request->cookie['tracking'])) {
                    $order_data['tracking'] = $this->request->cookie['tracking'];

                    $subtotal = $this->cart->getSubTotal();

                    // Affiliate
                    $affiliate_info = $this->model_account_customer->getAffiliateByTracking($this->request->cookie['tracking']);

                    if ($affiliate_info) {
                        $order_data['affiliate_id'] = $affiliate_info['customer_id'];
                        $order_data['commission'] = ($subtotal / 100) * $affiliate_info['commission'];
                    } else {
                        $order_data['affiliate_id'] = 0;
                        $order_data['commission'] = 0;
                    }

                    $marketing_info = $this->model_checkout_marketing->getMarketingByCode($this->request->cookie['tracking']);

                    if ($marketing_info) {
                        $order_data['marketing_id'] = $marketing_info['marketing_id'];
                    } else {
                        $order_data['marketing_id'] = 0;
                    }
                } else {
                    $order_data['affiliate_id'] = 0;
                    $order_data['commission'] = 0;
                    $order_data['marketing_id'] = 0;
                    $order_data['tracking'] = '';
                }

                $order_data['language_id'] = $this->config->get('config_language_id');
                $order_data['currency_id'] = $this->currency->getId(session('currency'));
                $order_data['currency_code'] = session('currency');
                //记录当前交易币种的汇率（美元是对美元的汇率，为1；英镑和欧元是对人民币的汇率，每日维护）
                $order_data['current_currency_value'] = $this->currency->getValue(session('currency'));
                // $order_data['currency_value'] = $this->currency->getValue(session('currency'));
                // lilei 修改oc_order表currency_value字段始终为1，该字段涉及account/order展示价格的部分
                $order_data['currency_value'] = 1;
                $order_data['ip'] = $this->request->server['REMOTE_ADDR'];

                if (!empty($this->request->server['HTTP_X_FORWARDED_FOR'])) {
                    $order_data['forwarded_ip'] = request()->serverBag->get('HTTP_X_FORWARDED_FOR');
                } elseif (!empty($this->request->server['HTTP_CLIENT_IP'])) {
                    $order_data['forwarded_ip'] = request()->serverBag->get('HTTP_CLIENT_IP');
                } else {
                    $order_data['forwarded_ip'] = '';
                }

                $order_data['user_agent'] = request()->serverBag->get('HTTP_USER_AGENT', '');

                $order_data['accept_language'] = request()->serverBag->get('HTTP_ACCEPT_LANGUAGE', '');

                //oc_order 和 oc_order_product中新增了transaction_type 以及agreement_id
                $order_id = $this->model_checkout_order->addOrder($order_data);
                // 议价
                app(QuoteService::class)
                    ->addOrderQuote($products, $customer_id, $order_id, $countryId, customer()->isEurope());

                session()->set('order_id', $order_id);
                $date['timestamp'] = date('YmdHis', time());
                // 优惠券设置为已使用
                $this->couponService->setCouponUsed($order_id, $couponId, $subItemsAndServiceFeeTotal);
                // 记录订单参与的促销活动
                app(CampaignService::class)->addCampaignOrder(array_merge($campaigns, $totalData['gifts']), $order_id);
                //预扣库存
                $this->model_checkout_order->withHoldStock($order_id);
                //下单页成功后需要进行预绑定，之后需要清除购物车对应数据
                $this->associateOrderAdvance($order_id,$run_id,$customer_id);
                // 创建仓租的费用单
                $feeOrderRunId = app(FeeOrderService::class)->generateFeeOrderRunId();
                $feeOrderList = array_values($this->model_account_sales_order_match_inventory_window->createStorageFeeOrderByPurchaseList($run_id, $customer_id, $totalStorageFee, $feeOrderRunId));
                // 创建保单的费用单
                if ($safeguards) {
                    $safeguards = array_combine(array_keys($safeguards), array_column($safeguards, 'safeguard_config_id'));
                    foreach ($safeguards as $salesOrderId => $safeguardIds) {
                        $res = app(SafeguardConfigRepository::class)->checkCanBuySafeguardBuSalesOrder($salesOrderId,$safeguardIds);
                        if (!$res['success']) {
                            throw new Exception('Information shown on this screen has been updated.  Please refresh this page.');
                        }
                    }
                    $safeguardsFeeOrderList = app(FeeOrderService::class)->createSafeguardFeeOrder($safeguards, $feeOrderRunId, $run_id);
                    $feeOrderList = array_merge($feeOrderList, $safeguardsFeeOrderList['need_pay_fee_order_list']);
                }

                // 销售订单选择使用囤货库存，需锁定囤货库存
                app(BuyerStockService::class)->inventoryLockBySalesOrderPreAssociated((string)$run_id, (int)$customer_id);

                $this->db->commit();
                $this->cart->deleteCart($cartIdArr);
                return $this->jsonSuccess([
                    'order_id' => $order_id,
                    'fee_order_list' => implode(',', $feeOrderList)
                ], 'Create order successfully');
            } catch (AssociatedPreException $e) {
                $this->db->rollback();
                $this->cart->deleteCart($cartIdArr);
                return $this->jsonFailed('The information displayed on this page has been updated. Please go back to the Sales Order list page to make the payment again.', [], 1);
            } catch (Exception $e) {
                $this->db->rollback();
                //记录日志
                $this->log->write('Create Order Error Order id:' . $order_id . ',' . $e->getMessage());
                $this->log->write($e->getMessage());
                $this->cart->deleteCart($cartIdArr);
                $msg = $e->getMessage();
            }
        }
        return $this->jsonFailed($msg, ['url' => $redirect]);
    }

    /**
     * @param int $order_id oc_order表的order_id
     * @param int|string $run_id
     * @param int $customer_id BuyerId
     * @return mixed ['storage_fee_order' => [salesOrderId => feeOrderId]]
     * @throws Exception
     */
    private function associateOrderAdvance($order_id,$run_id,$customer_id){
        $this->load->model('account/sales_order/match_inventory_window');
        $matchModel = $this->model_account_sales_order_match_inventory_window;
        $matchModel->checkStockpileStockBuPurchaseList($run_id, $customer_id);
        //预绑定库存
        $matchModel->associateOrder($order_id,$run_id,$customer_id);
    }

    /**
     * 判断是不是欧洲账户
     * @return bool
     */
    public function isEuropeanAccount()
    {
        if (!empty($this->customer->getCountryId()) && $this->country->isEuropeCountry($this->customer->getCountryId())) {
            $isEuropean = true;
        } else {
            $isEuropean = false;
        }
        return $isEuropean;
    }

}
