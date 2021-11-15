<?php

use App\Enums\FeeOrder\FeeOrderStatus;
use App\Enums\Pay\PayCode;
use App\Enums\SalesOrder\CustomerSalesOrderStatus;
use App\Logging\Logger;
use App\Repositories\FeeOrder\FeeOrderRepository;
use App\Repositories\FeeOrder\StorageFeeRepository;
use App\Repositories\SalesOrder\AutoBuyRepository;
use App\Services\FeeOrder\FeeOrderService;
use App\Services\FeeOrder\StorageFeeService;
use App\Services\Marketing\CampaignService;
use App\Services\Marketing\CouponService;
use App\Services\Quote\QuoteService;
use App\Services\SalesOrder\SalesOrderService;
use Cart\Customer;

/**
 * @property ModelAccountCustomer $model_account_customer
 * @property ModelCheckoutOrder $model_checkout_order
 * @property ModelCheckoutCart $model_checkout_cart
 * @property ModelAccountAddress $model_account_address
 * @property ModelAccountBalanceVirtualPayRecord $model_account_balance_virtual_pay_record
 * @property ModelCommonProduct $model_common_product
 * @property ModelCheckoutPay $model_checkout_pay
 * @property ModelLocalisationCurrency $model_localisation_currency
 * @property ModelSettingExtension $model_setting_extension
 * */
class ControllerApiOrder extends Controller
{
    const START_API_ORDER = '----API ORDER START----';
    const END_API_ORDER = '----API ORDER END----';
    private $sales__model;

    public function __construct(Registry $registry)
    {
        parent::__construct($registry);
        $this->sales__model = new Catalog\model\account\sales_order\SalesOrderManagement($registry);
        $this->load->model('common/product');
        Logger::order(['request', $this->request->getMethod(), $this->request->attributes->all()]);
    }

    public function add()
    {
        $this->load->language('api/order');

        $json = array();

        if (!isset($this->session->data['api_id'])) {
            $json['error'] = $this->language->get('error_permission');
        } else {
            // Customer
            if (!isset($this->session->data['customer'])) {
                $json['error'] = $this->language->get('error_customer');
            }

            // Payment Address
            if (!isset($this->session->data['payment_address'])) {
                $json['error'] = $this->language->get('error_payment_address');
            }

            // Payment Method
            if (!$json && !empty($this->request->post['payment_method'])) {
                if (empty($this->session->data['payment_methods'])) {
                    $json['error'] = $this->language->get('error_no_payment');
                } elseif (!isset($this->session->data['payment_methods'][$this->request->post['payment_method']])) {
                    $json['error'] = $this->language->get('error_payment_method');
                }

                if (!$json) {
                    $this->session->data['payment_method'] = $this->session->data['payment_methods'][$this->request->post['payment_method']];
                }
            }

            if (!isset($this->session->data['payment_method'])) {
                $json['error'] = $this->language->get('error_payment_method');
            }

            // Shipping
            if ($this->cart->hasShipping()) {
                // Shipping Address
                if (!isset($this->session->data['shipping_address'])) {
                    $json['error'] = $this->language->get('error_shipping_address');
                }

                // Shipping Method
                if (!$json && !empty($this->request->post['shipping_method'])) {
                    if (empty($this->session->data['shipping_methods'])) {
                        $json['error'] = $this->language->get('error_no_shipping');
                    } else {
                        $shipping = explode('.', $this->request->post['shipping_method']);

                        if (!isset($shipping[0]) || !isset($shipping[1]) || !isset($this->session->data['shipping_methods'][$shipping[0]]['quote'][$shipping[1]])) {
                            $json['error'] = $this->language->get('error_shipping_method');
                        }
                    }

                    if (!$json) {
                        $this->session->data['shipping_method'] = $this->session->data['shipping_methods'][$shipping[0]]['quote'][$shipping[1]];
                    }
                }

                // Shipping Method
                if (!isset($this->session->data['shipping_method'])) {
                    $json['error'] = $this->language->get('error_shipping_method');
                }
            } else {
                $this->session->remove('shipping_address');
                $this->session->remove('shipping_method');
                $this->session->remove('shipping_methods');
            }

            // Cart
            if ((!$this->cart->hasProducts() && empty($this->session->data['vouchers'])) || (!$this->cart->hasStock() && !$this->config->get('config_stock_checkout'))) {
                $json['error'] = $this->language->get('error_stock');
            }

            //add by xss 欧洲自动购买收取手续费，并且oc_order_product中记录的价格为price*0.85/2之后的价格
            $this->session->data['customer_id'] = $this->session->data['customer']['customer_id'];
            $customer = new Cart\Customer($this->registry);
            $this->registry->set('customer', $customer);

            // Validate minimum quantity requirements.
            $products = $this->cart->getProducts();

            foreach ($products as $product) {
                $product_total = 0;

                foreach ($products as $product_2) {
                    if ($product_2['product_id'] == $product['product_id']) {
                        $product_total += $product_2['quantity'];
                    }
                }

                if ($product['minimum'] > $product_total) {
                    $json['error'] = sprintf($this->language->get('error_minimum'), $product['name'], $product['minimum']);

                    break;
                }
            }

            if (!$json) {
                $json['success'] = $this->language->get('text_success');

                $order_data = array();

                // Store Details
                $order_data['invoice_prefix'] = $this->config->get('config_invoice_prefix');
                $order_data['store_id'] = $this->config->get('config_store_id');
                $order_data['store_name'] = $this->config->get('config_name');
                $order_data['store_url'] = $this->config->get('config_url');

                // Customer Details
                $order_data['customer_id'] = $this->session->data['customer']['customer_id'];
                $order_data['customer_group_id'] = $this->session->data['customer']['customer_group_id'];
                $order_data['firstname'] = $this->session->data['customer']['firstname'];
                $order_data['lastname'] = $this->session->data['customer']['lastname'];
                $order_data['email'] = $this->session->data['customer']['email'];
                $order_data['telephone'] = $this->session->data['customer']['telephone'];
                $order_data['custom_field'] = $this->session->data['customer']['custom_field'];

                // Payment Details
                $order_data['payment_firstname'] = $this->session->data['payment_address']['firstname'];
                $order_data['payment_lastname'] = $this->session->data['payment_address']['lastname'];
                $order_data['payment_company'] = $this->session->data['payment_address']['company'];
                $order_data['payment_address_1'] = $this->session->data['payment_address']['address_1'];
                $order_data['payment_address_2'] = $this->session->data['payment_address']['address_2'];
                $order_data['payment_city'] = $this->session->data['payment_address']['city'];
                $order_data['payment_postcode'] = $this->session->data['payment_address']['postcode'];
                $order_data['payment_zone'] = $this->session->data['payment_address']['zone'];
                $order_data['payment_zone_id'] = $this->session->data['payment_address']['zone_id'];
                $order_data['payment_country'] = $this->session->data['payment_address']['country'];
                $order_data['payment_country_id'] = $this->session->data['payment_address']['country_id'];
                $order_data['payment_address_format'] = $this->session->data['payment_address']['address_format'];
                $order_data['payment_custom_field'] = (isset($this->session->data['payment_address']['custom_field']) ? $this->session->data['payment_address']['custom_field'] : array());

                if (isset($this->session->data['payment_method']['title'])) {
                    $order_data['payment_method'] = $this->session->data['payment_method']['title'];
                } else {
                    $order_data['payment_method'] = '';
                }

                if (isset($this->session->data['payment_method']['code'])) {
                    $order_data['payment_code'] = $this->session->data['payment_method']['code'];
                } else {
                    $order_data['payment_code'] = '';
                }

                // Shipping Details
                if ($this->cart->hasShipping()) {
                    $order_data['shipping_firstname'] = $this->session->data['shipping_address']['firstname'];
                    $order_data['shipping_lastname'] = $this->session->data['shipping_address']['lastname'];
                    $order_data['shipping_company'] = $this->session->data['shipping_address']['company'];
                    $order_data['shipping_address_1'] = $this->session->data['shipping_address']['address_1'];
                    $order_data['shipping_address_2'] = $this->session->data['shipping_address']['address_2'];
                    $order_data['shipping_city'] = $this->session->data['shipping_address']['city'];
                    $order_data['shipping_postcode'] = $this->session->data['shipping_address']['postcode'];
                    $order_data['shipping_zone'] = $this->session->data['shipping_address']['zone'];
                    $order_data['shipping_zone_id'] = $this->session->data['shipping_address']['zone_id'];
                    $order_data['shipping_country'] = $this->session->data['shipping_address']['country'];
                    $order_data['shipping_country_id'] = $this->session->data['shipping_address']['country_id'];
                    $order_data['shipping_address_format'] = $this->session->data['shipping_address']['address_format'];
                    $order_data['shipping_custom_field'] = (isset($this->session->data['shipping_address']['custom_field']) ? $this->session->data['shipping_address']['custom_field'] : array());

                    if (isset($this->session->data['shipping_method']['title'])) {
                        $order_data['shipping_method'] = $this->session->data['shipping_method']['title'];
                    } else {
                        $order_data['shipping_method'] = '';
                    }

                    if (isset($this->session->data['shipping_method']['code'])) {
                        $order_data['shipping_code'] = $this->session->data['shipping_method']['code'];
                    } else {
                        $order_data['shipping_code'] = '';
                    }
                } else {
                    $order_data['shipping_firstname'] = '';
                    $order_data['shipping_lastname'] = '';
                    $order_data['shipping_company'] = '';
                    $order_data['shipping_address_1'] = '';
                    $order_data['shipping_address_2'] = '';
                    $order_data['shipping_city'] = '';
                    $order_data['shipping_postcode'] = '';
                    $order_data['shipping_zone'] = '';
                    $order_data['shipping_zone_id'] = '';
                    $order_data['shipping_country'] = '';
                    $order_data['shipping_country_id'] = '';
                    $order_data['shipping_address_format'] = '';
                    $order_data['shipping_custom_field'] = array();
                    $order_data['shipping_method'] = '';
                    $order_data['shipping_code'] = '';
                }

                // Products
                $order_data['products'] = array();

                foreach ($this->cart->getProducts() as $product) {
                    $option_data = array();

                    foreach ($product['option'] as $option) {
                        $option_data[] = array(
                            'product_option_id' => $option['product_option_id'],
                            'product_option_value_id' => $option['product_option_value_id'],
                            'option_id' => $option['option_id'],
                            'option_value_id' => $option['option_value_id'],
                            'name' => $option['name'],
                            'value' => $option['value'],
                            'type' => $option['type']
                        );
                    }

                    if (($product['discountPrice'] - round($product['price'], 2)) < 0) {
                        $serviceFeePer = 0;
                    } else {
                        $serviceFeePer = ($product['discountPrice'] - round($product['price'], 2)) * $product['quantity'];
                    }

                    $order_data['products'][] = array(
                        'product_id' => $product['product_id'],
                        'name' => $product['name'],
                        'model' => $product['model'],
                        'option' => $option_data,
                        'download' => $product['download'],
                        'quantity' => $product['quantity'],
                        'subtract' => $product['subtract'],
                        'price' => $product['price'],
                        'serviceFeePer' => $serviceFeePer,
                        'serviceFee' => $serviceFeePer * $product['quantity'],
                        'total' => $product['total'],
                        'tax' => $this->tax->getTax($product['price'], $product['tax_class_id']),
                        'reward' => $product['reward'],
                        'danger_flag' => $product['danger_flag'] ?? 0,
                    );
                }

                // Gift Voucher
                $order_data['vouchers'] = array();

                if (!empty($this->session->data['vouchers'])) {
                    foreach ($this->session->data['vouchers'] as $voucher) {
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

                // Order Totals
                $this->load->model('setting/extension');

                $totals = array();
                $taxes = $this->cart->getTaxes();
                $total = 0;

                // Because __call can not keep var references so we put them into an array.
                $total_data = array(
                    'totals' => &$totals,
                    'taxes' => &$taxes,
                    'total' => &$total
                );

                $sort_order = array();

                $results = $this->model_setting_extension->getExtensions('total');

                foreach ($results as $key => $value) {
                    $sort_order[$key] = $this->config->get('total_' . $value['code'] . '_sort_order');
                }

                array_multisort($sort_order, SORT_ASC, $results);

                foreach ($results as $result) {
                    if ($this->config->get('total_' . $result['code'] . '_status')) {
                        $this->load->model('extension/total/' . $result['code']);

                        // We have to put the totals in an array so that they pass by reference.
                        $this->{'model_extension_total_' . $result['code']}->getTotal($total_data);
                    }
                }

                $sort_order = array();

                foreach ($total_data['totals'] as $key => $value) {
                    $sort_order[$key] = $value['sort_order'];
                }

                array_multisort($sort_order, SORT_ASC, $total_data['totals']);

                $order_data = array_merge($order_data, $total_data);

                if (isset($this->request->post['comment'])) {
                    $order_data['comment'] = $this->request->post['comment'];
                } else {
                    $order_data['comment'] = '';
                }

                if (isset($this->request->post['affiliate_id'])) {
                    $subtotal = $this->cart->getSubTotal();

                    // Affiliate
                    $this->load->model('account/customer');

                    $affiliate_info = $this->model_account_customer->getAffiliate($this->request->post['affiliate_id']);

                    if ($affiliate_info) {
                        $order_data['affiliate_id'] = $affiliate_info['customer_id'];
                        $order_data['commission'] = ($subtotal / 100) * $affiliate_info['commission'];
                    } else {
                        $order_data['affiliate_id'] = 0;
                        $order_data['commission'] = 0;
                    }

                    // Marketing
                    $order_data['marketing_id'] = 0;
                    $order_data['tracking'] = '';
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
                //addby xiesense 记录汇率为1
                /*$order_data['currency_value'] = $this->currency->getValue(session('currency'));*/
                $order_data['currency_value'] = 1;
                $order_data['ip'] = request()->serverBag->get('REMOTE_ADDR');

                if (!empty($this->request->server['HTTP_X_FORWARDED_FOR'])) {
                    $order_data['forwarded_ip'] = request()->serverBag->get('HTTP_X_FORWARDED_FOR');
                } elseif (!empty($this->request->server['HTTP_CLIENT_IP'])) {
                    $order_data['forwarded_ip'] = request()->serverBag->get('HTTP_CLIENT_IP');
                } else {
                    $order_data['forwarded_ip'] = '';
                }

                $order_data['user_agent'] = request()->serverBag->get('HTTP_USER_AGENT', '');

                $order_data['accept_language'] = request()->serverBag->get('HTTP_ACCEPT_LANGUAGE', '');

                $this->load->model('checkout/order');

                $json['order_id'] = $this->model_checkout_order->addOrder($order_data);

                // Set the order history
                if (isset($this->request->post['order_status_id'])) {
                    $order_status_id = $this->request->post['order_status_id'];
                } else {
                    $order_status_id = $this->config->get('config_order_status_id');
                }

                $this->model_checkout_order->addOrderHistory($json['order_id'], $order_status_id);

                // clear cart since the order has already been successfully stored.
                $this->cart->clear();
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * @deprecated 走新的自动购买逻辑
     * @see ControllerApiAutoPurchase::index() 入口
     * @see OrderComponent::handle() 购买逻辑
     */
    public function apiAdd()
    {
        Logger::order(static::START_API_ORDER, 'info', [
            Logger::CONTEXT_WEB_SERVER_VARS => ['_REQUEST', '_SERVER'],
        ]);
        set_time_limit(0);
        $this->load->language('api/order');

        $json = array();
        if (!($this->session->get('api_id'))) {
            $json['error'] = $this->language->get('error_permission');
        }

        $buyer_id = $this->request->post('buyer_id', 0);
        if (!$buyer_id) {
            $json['error'] = $this->language->get('error_customer');
        }

        // tb_sys_customer_sales_order 主键
        $sale_order_id = $this->request->post('sale_orderId', 0);
        if (!$sale_order_id) {
            $json['error'] = $this->language->get('error_sale_order');
        }
        if (isset($json['error'])) {
            $this->session->set('api_id', 1);
            Logger::order([static::END_API_ORDER, 'json' => $json]);
            $this->cart->clearWithBuyerId($buyer_id);
            $this->removeAssociateAndComboInfo($sale_order_id);
            return $this->response->json($json);
        } else {
            $this->load->model('account/customer');
            $this->load->model('localisation/currency');
            //费用单订单
            $feeOrderStr = $this->request->post('fee_order_list', '[]');
            $feeOrderArr = empty($feeOrderStr) ? [] : json_decode($feeOrderStr, true);
            $feeOrderInfos = app(FeeOrderRepository::class)->findFeeOrderInfo($feeOrderArr);
            //原先的购物车校验
            $cartFlag = (!$this->cart->hasProductsWithBuyerId($buyer_id) && empty($this->session->get('vouchers'))) || (!$this->cart->hasStockWithBuyerId($buyer_id) && !$this->config->get('config_stock_checkout'));
            if (empty($feeOrderInfos) && $cartFlag) {

                $json['error'] = $this->language->get('error_stock');
            }
            // Cart
            //            if ((!$this->cart->hasProductsWithBuyerId($buyer_id) && empty($this->session->get('vouchers')))
            //                || (!$this->cart->hasStockWithBuyerId($buyer_id) && !$this->config->get('config_stock_checkout'))) {
            //                $json['error'] = $this->language->get('error_stock');
            //            }

            $currency_info = $this->model_localisation_currency->getCurrencyByCode($this->request->post('currency'));
            if (!isset($currency_info)) {
                $json['error'] = $this->language->get('error_currency');
            }

            // Payment Method
            $paymentCode = PayCode::PAY_LINE_OF_CREDIT;
            $paymentMethod = PayCode::getDescriptionWithPoundage($paymentCode);
            $cartIdArr = $this->cart->getAutoBuyCartId($buyer_id);
            $delivery_type = $this->session->get('delivery_type', 0);
            $this->session->set('customer_id', $buyer_id);
            $customer = new Customer($this->registry);
            $this->registry->set('customer', $customer);
            $innerAutoBuyAttr1 = $this->customer->innerAutoBuyAttr1();//内部自动购买采销异体账号
            if ($innerAutoBuyAttr1) {
                $this->load->model('checkout/cart');
                $check = $this->model_checkout_cart->checkPurchaseAndSales($delivery_type);
                if (empty($check) && empty($feeOrderInfos)) {
                    $json['error'] = $this->language->get('error_match');
                } else {
                    $cartIdArr = $this->model_checkout_cart->updateInnerAutoBuyCart($delivery_type);
                    if ($cartIdArr || !empty($feeOrderInfos)) {
                        $paymentCode = PayCode::PAY_VIRTUAL;
                        $paymentMethod = PayCode::getDescriptionWithPoundage($paymentCode);
                    } else {
                        $json['error'] = $this->language->get('error_match');
                    }
                }
            }
            //内部 自动购买-FBA自提 只支持虚拟支付
            if ($this->customer->isInnerFBA() && PayCode::PAY_VIRTUAL != $paymentCode) {
                $json['error'] = '内部 自动购买-FBA自提 只支持虚拟支付';
            }
            // 校验要购买的明细的是否在销售订单明细范围之内
            if (!app(AutoBuyRepository::class)->checkSaleOder($cartIdArr, $buyer_id, $sale_order_id)) {
                $json['error'] = '购买明细超出销售订单范围';
            }
            if ($json) {
                Logger::order([static::END_API_ORDER, 'json' => $json]);
                $this->cart->clearWithBuyerId($buyer_id);
                $this->removeAssociateAndComboInfo($sale_order_id);
                return $this->response->json($json);
            }

            // Validate minimum quantity requirements.
            $products = $this->cart->getProducts($buyer_id, $delivery_type, $cartIdArr);

            // 记录当前购物车中的所有内容
            Logger::order(['api/cart/order', 'info',
                Logger::CONTEXT_VAR_DUMPER => ['cartInfo' => $products ],
            ]);

            $totalTest = 0;
            foreach ($products as $product) {
                if (!$this->config->get('config_stock_checkout') && !$product['stock']) {
                    $json['error'] = $this->language->get('error_stock');
                    break;
                }
                if ($product['minimum'] > $product['quantity']) {
                    $json['error'] = sprintf($this->language->get('error_minimum'), $product['name'], $product['minimum']);
                    break;
                }
                $totalTest = $totalTest + $product['total'];
            }

            // 校验商品在库库存是否满足需求
            // 忽略尾款产品的校验 尾款产品的校验放在出库
            $resolved_product = [];
            foreach ($products as $product) {
                if (($product['product_type'] == 0 || $product['product_type'] == 3) && in_array($product['type_id'], [0, 1, 4])) {
                    $resolved_product[] = $product;
                }
            }
            if (!$this->model_common_product->checkProductQuantityValid($resolved_product)) {
                $json['error'] = 'Product stock quantity not available.';
            }
            //费用单计算金额
            if (!empty($feeOrderInfos)) {
                $feeOrderTotal = app(FeeOrderRepository::class)->findFeeOrderTotal($feeOrderArr);
                $totalTest = $totalTest + $feeOrderTotal;
            }
            $customer_info = $this->model_account_customer->getCustomer($buyer_id);
            $credit = $customer_info['line_of_credit'];
            if (PayCode::PAY_LINE_OF_CREDIT == $paymentCode && $credit < $totalTest) {
                $json['error'] = $this->language->get('error_line_credit');
            }

            // Order Totals
            $this->load->model('setting/extension');

            $total = 0;
            $totals = array();
            $taxes = $this->cart->getTaxesWithBuyerId($buyer_id);
            // 优惠券和促销活动处理
            $this->load->model('account/cart/cart');
            $totalData = $this->model_account_cart_cart->orderTotalShow($products);
            $precision = $this->customer->getCountryId() == JAPAN_COUNTRY_ID ? 0 : 2;
            $collection = collect($totalData['totals'])->keyBy('code');
            $campaigns = $collection['promotion_discount']['discounts'] ?? []; // 促销活动
            $subItemsTotal = $collection['sub_total']['value']; // 总货值金额
            $serviceFeeTotal = $collection['service_fee']['value'] ?? 0; // 总服务费
            $subItemsAndServiceFeeTotal = bcadd($subItemsTotal, $serviceFeeTotal, 4); //总货值金额+总服务费
            $couponId = $totalData['select_coupon_ids'][0] ?? 0;
            // 计算每个产品占用满减份额
            $campaignPerDiscount = app(CampaignService::class)->calculateMutiCampaignDiscount($campaigns, $products, $precision);
            $couponPerDiscount = [];
            // 计算每个产品占用优惠券份额
            if (isset($collection['giga_coupon']) && $collection['giga_coupon']) {
                $couponPerDiscount = app(couponService::class)->calculateCouponDiscount(abs($collection['giga_coupon']['value']), $products, $precision);
            }
            $total_data = array(
                'totals' => &$totals,
                'taxes' => &$taxes,
                'total' => &$total
            );

            $sort_order = array();
            $results = $this->model_setting_extension->getExtensions('total');

            foreach ($results as $key => $value) {
                $sort_order[$key] = $this->config->get('total_' . $value['code'] . '_sort_order');
            }

            array_multisort($sort_order, SORT_ASC, $results);
            if (!empty($cartIdArr)) {
                $products = array_map(function ($product) {
                    $product['current_price'] = $product['price'];
                    $product['transaction_type'] = $product['type_id'];
                    return $product;
                }, $products);
                foreach ($results as $result) {
                    if ($this->config->get('total_' . $result['code'] . '_status')) {
                        $this->load->model('extension/total/' . $result['code']);

                        if (!empty($cartIdArr)) {
                            $this->{'model_extension_total_' . $result['code']}->getTotalByCartId($total_data, $products);
                        } else {
                            $this->{'model_extension_total_' . $result['code']}->getTotal($total_data, $buyer_id);
                        }
                    }
                }
            }
            $sort_order = array();

            foreach ($total_data['totals'] as $key => $value) {
                $sort_order[$key] = $value['sort_order'];
            }

            array_multisort($sort_order, SORT_ASC, $total_data['totals']);

            if (PayCode::PAY_LINE_OF_CREDIT == $paymentCode && $credit < $total_data['total']) {
                $json['error'] = $this->language->get('error_line_credit');
            }
            if (!app(AutoBuyRepository::class)->checkSubToal($total_data, $products, $this->customer->isEurope())) {
                $json['error'] = 'sub total error';
            }

            if ($json) {
                Logger::order([static::END_API_ORDER, 'json' => $json]);
                $this->cart->clearWithBuyerId($buyer_id);
                $this->removeAssociateAndComboInfo($sale_order_id);
                return $this->response->json($json);
            }

            try {
                $this->db->beginTransaction();
                $this->load->model('checkout/order');
                $this->load->model('checkout/pay');
                Logger::order(['order info', 'info',
                    Logger::CONTEXT_VAR_DUMPER => [
                        'total_data' => $total_data,
                        'paymentMethod' => $paymentMethod,
                        'paymentCode' => $paymentCode,
                        'currency_info' => $currency_info,
                    ],
                ]);// 按照可视化形式输出
                //内部采销异体使用虚拟支付，其他购买目前只支持信用额度方式
                if (!empty($cartIdArr)) {
                    $order_data = $this->createOrderData($products, $total_data, $customer_info, $buyer_id, $paymentMethod, $paymentCode, $currency_info, $couponPerDiscount, $campaignPerDiscount);
                    $json['order_id'] = $this->model_checkout_order->addOrder($order_data, $sale_order_id);
                    // 下面添加议价数据的方法有用到
                    foreach ($products as &$product) {
                        $product['spot_price'] = $product['quote_amount'] ?? 0;
                    }
                    unset($product);
                    // 议价相关数据插入
                    app(QuoteService::class)->addOrderQuote($products, $buyer_id, $json['order_id'], customer()->getCountryId(), customer()->isEurope());
                    $orderData = [
                        'order_id' => $json['order_id'],
                        'balance' => PayCode::PAY_VIRTUAL == $paymentCode ? 0 : $total_data['total'],
                        'poundage' => 0,
                        'order_total' => $total_data['total']
                    ];
                    $this->model_checkout_pay->updateOrderTotal($orderData);
                }
                $orderId = isset($json['order_id']) ? $json['order_id'] : 0;
                $this->model_checkout_order->updateFeeOrderPayment($feeOrderArr, $paymentCode, $paymentMethod);
                if (PayCode::PAY_VIRTUAL == $paymentCode) {
                    $this->load->model('account/balance/virtual_pay_record');
                    if (!empty($json['order_id'])) {
                        $this->model_account_balance_virtual_pay_record->insertData($buyer_id, $json['order_id'], $total, 1);
                    }
                    if (!empty($feeOrderInfos)) {
                        $feeOrderTotalArr = array_column($feeOrderInfos, 'fee_total', 'id');
                        foreach ($feeOrderArr as $feeOrder) {
                            $this->model_account_balance_virtual_pay_record->insertData($buyer_id, $feeOrder, $feeOrderTotalArr[$feeOrder], 4);
                        }
                    }

                } else {
                    $this->model_checkout_order->payByLineOfCredit($orderId, $feeOrderArr, $buyer_id);//组合支付的时候
                }

                //库存预出库
                if (!empty($orderId)) {
                    $this->model_checkout_order->withHoldStock($orderId);
                    // 优惠券设置为已使用
                    app(couponService::class)->setCouponUsed($orderId, $couponId, $subItemsAndServiceFeeTotal);
                    // 记录订单参与的促销活动
                    app(CampaignService::class)->addCampaignOrder(array_merge($campaigns, $totalData['gifts']), $orderId);
                }
                //自动购买，目前只支持自营商品，购买完成，订单完毕。状态码5
                $this->model_checkout_order->addOrderHistoryByYzcModel($orderId, $feeOrderArr, 5, '', false, false, $sale_order_id);
                $this->db->commit();
                $this->model_checkout_order->addOrderHistoryByYzcModelAfterCommit($orderId);
                $json['success'] = $this->language->get('create_success');

            } catch (Exception $e) {
                $this->db->rollback();
                //记录日志
                Logger::order($e, 'error');
                if ($e->getCode() == 999) {
                    $json['error'] = $this->language->get('error_stock');
                } else {
                    if (isset($json['order_id'])) {
                        $msg = 'Create Order Error Order id:' . $json['order_id'] . ',' . $e->getMessage();
                    } else {
                        $msg = 'Create Order Failed.';
                    }
                    Logger::order($msg, 'error');
                }
                $json['error'] = $this->language->get('error_exception');
                $this->removeAssociateAndComboInfo($sale_order_id);
                //回退销售订单的状态
                app(SalesOrderService::class)->updateSalesOrderStatus($sale_order_id,CustomerSalesOrderStatus::TO_BE_PAID);
                $this->cart->clearWithBuyerId($buyer_id);
                //解绑仓租，取消费用单
                app(StorageFeeService::class)->unbindBySalesOrder([$sale_order_id]);
                $feeOrderService = app(FeeOrderService::class);
                foreach ($feeOrderArr as $feeOrderId) {
                    $feeOrderService->changeFeeOrderStatus($feeOrderId, FeeOrderStatus::EXPIRED);
                }
            }
        }
        Logger::order([static::END_API_ORDER, 'json' => $json]);
        return $this->response->json($json);
    }

    public function edit()
    {
        $this->load->language('api/order');

        $json = array();

        if (!isset($this->session->data['api_id'])) {
            $json['error'] = $this->language->get('error_permission');
        } else {
            $this->load->model('checkout/order');

            if (isset($this->request->get['order_id'])) {
                $order_id = $this->request->get['order_id'];
            } else {
                $order_id = 0;
            }

            $order_info = $this->model_checkout_order->getOrder($order_id);

            if ($order_info) {
                // Customer
                if (!isset($this->session->data['customer'])) {
                    $json['error'] = $this->language->get('error_customer');
                }

                // Payment Address
                if (!isset($this->session->data['payment_address'])) {
                    $json['error'] = $this->language->get('error_payment_address');
                }

                // Payment Method
                if (!$json && !empty($this->request->post['payment_method'])) {
                    if (empty($this->session->data['payment_methods'])) {
                        $json['error'] = $this->language->get('error_no_payment');
                    } elseif (!isset($this->session->data['payment_methods'][$this->request->post['payment_method']])) {
                        $json['error'] = $this->language->get('error_payment_method');
                    }

                    if (!$json) {
                        $this->session->data['payment_method'] = $this->session->data['payment_methods'][$this->request->post['payment_method']];
                    }
                }

                if (!isset($this->session->data['payment_method'])) {
                    $json['error'] = $this->language->get('error_payment_method');
                }

                // Shipping
                if ($this->cart->hasShipping()) {
                    // Shipping Address
                    if (!isset($this->session->data['shipping_address'])) {
                        $json['error'] = $this->language->get('error_shipping_address');
                    }

                    // Shipping Method
                    if (!$json && !empty($this->request->post['shipping_method'])) {
                        if (empty($this->session->data['shipping_methods'])) {
                            $json['error'] = $this->language->get('error_no_shipping');
                        } else {
                            $shipping = explode('.', $this->request->post['shipping_method']);

                            if (!isset($shipping[0]) || !isset($shipping[1]) || !isset($this->session->data['shipping_methods'][$shipping[0]]['quote'][$shipping[1]])) {
                                $json['error'] = $this->language->get('error_shipping_method');
                            }
                        }

                        if (!$json) {
                            $this->session->data['shipping_method'] = $this->session->data['shipping_methods'][$shipping[0]]['quote'][$shipping[1]];
                        }
                    }

                    if (!isset($this->session->data['shipping_method'])) {
                        $json['error'] = $this->language->get('error_shipping_method');
                    }
                } else {
                    $this->session->remove('shipping_address');
                    $this->session->remove('shipping_method');
                    $this->session->remove('shipping_methods');
                }

                // Cart
                if ((!$this->cart->hasProducts() && empty($this->session->data['vouchers'])) || (!$this->cart->hasStock() && !$this->config->get('config_stock_checkout'))) {
                    $json['error'] = $this->language->get('error_stock');
                }

                // Validate minimum quantity requirements.
                $products = $this->cart->getProducts();

                foreach ($products as $product) {
                    $product_total = 0;

                    foreach ($products as $product_2) {
                        if ($product_2['product_id'] == $product['product_id']) {
                            $product_total += $product_2['quantity'];
                        }
                    }

                    if ($product['minimum'] > $product_total) {
                        $json['error'] = sprintf($this->language->get('error_minimum'), $product['name'], $product['minimum']);

                        break;
                    }
                }

                if (!$json) {
                    $json['success'] = $this->language->get('text_success');

                    $order_data = array();

                    // Store Details
                    $order_data['invoice_prefix'] = $this->config->get('config_invoice_prefix');
                    $order_data['store_id'] = $this->config->get('config_store_id');
                    $order_data['store_name'] = $this->config->get('config_name');
                    $order_data['store_url'] = $this->config->get('config_url');

                    // Customer Details
                    $order_data['customer_id'] = $this->session->data['customer']['customer_id'];
                    $order_data['customer_group_id'] = $this->session->data['customer']['customer_group_id'];
                    $order_data['firstname'] = $this->session->data['customer']['firstname'];
                    $order_data['lastname'] = $this->session->data['customer']['lastname'];
                    $order_data['email'] = $this->session->data['customer']['email'];
                    $order_data['telephone'] = $this->session->data['customer']['telephone'];
                    $order_data['custom_field'] = $this->session->data['customer']['custom_field'];

                    // Payment Details
                    $order_data['payment_firstname'] = $this->session->data['payment_address']['firstname'];
                    $order_data['payment_lastname'] = $this->session->data['payment_address']['lastname'];
                    $order_data['payment_company'] = $this->session->data['payment_address']['company'];
                    $order_data['payment_address_1'] = $this->session->data['payment_address']['address_1'];
                    $order_data['payment_address_2'] = $this->session->data['payment_address']['address_2'];
                    $order_data['payment_city'] = $this->session->data['payment_address']['city'];
                    $order_data['payment_postcode'] = $this->session->data['payment_address']['postcode'];
                    $order_data['payment_zone'] = $this->session->data['payment_address']['zone'];
                    $order_data['payment_zone_id'] = $this->session->data['payment_address']['zone_id'];
                    $order_data['payment_country'] = $this->session->data['payment_address']['country'];
                    $order_data['payment_country_id'] = $this->session->data['payment_address']['country_id'];
                    $order_data['payment_address_format'] = $this->session->data['payment_address']['address_format'];
                    $order_data['payment_custom_field'] = $this->session->data['payment_address']['custom_field'];

                    if (isset($this->session->data['payment_method']['title'])) {
                        $order_data['payment_method'] = $this->session->data['payment_method']['title'];
                    } else {
                        $order_data['payment_method'] = '';
                    }

                    if (isset($this->session->data['payment_method']['code'])) {
                        $order_data['payment_code'] = $this->session->data['payment_method']['code'];
                    } else {
                        $order_data['payment_code'] = '';
                    }

                    // Shipping Details
                    if ($this->cart->hasShipping()) {
                        $order_data['shipping_firstname'] = $this->session->data['shipping_address']['firstname'];
                        $order_data['shipping_lastname'] = $this->session->data['shipping_address']['lastname'];
                        $order_data['shipping_company'] = $this->session->data['shipping_address']['company'];
                        $order_data['shipping_address_1'] = $this->session->data['shipping_address']['address_1'];
                        $order_data['shipping_address_2'] = $this->session->data['shipping_address']['address_2'];
                        $order_data['shipping_city'] = $this->session->data['shipping_address']['city'];
                        $order_data['shipping_postcode'] = $this->session->data['shipping_address']['postcode'];
                        $order_data['shipping_zone'] = $this->session->data['shipping_address']['zone'];
                        $order_data['shipping_zone_id'] = $this->session->data['shipping_address']['zone_id'];
                        $order_data['shipping_country'] = $this->session->data['shipping_address']['country'];
                        $order_data['shipping_country_id'] = $this->session->data['shipping_address']['country_id'];
                        $order_data['shipping_address_format'] = $this->session->data['shipping_address']['address_format'];
                        $order_data['shipping_custom_field'] = $this->session->data['shipping_address']['custom_field'];

                        if (isset($this->session->data['shipping_method']['title'])) {
                            $order_data['shipping_method'] = $this->session->data['shipping_method']['title'];
                        } else {
                            $order_data['shipping_method'] = '';
                        }

                        if (isset($this->session->data['shipping_method']['code'])) {
                            $order_data['shipping_code'] = $this->session->data['shipping_method']['code'];
                        } else {
                            $order_data['shipping_code'] = '';
                        }
                    } else {
                        $order_data['shipping_firstname'] = '';
                        $order_data['shipping_lastname'] = '';
                        $order_data['shipping_company'] = '';
                        $order_data['shipping_address_1'] = '';
                        $order_data['shipping_address_2'] = '';
                        $order_data['shipping_city'] = '';
                        $order_data['shipping_postcode'] = '';
                        $order_data['shipping_zone'] = '';
                        $order_data['shipping_zone_id'] = '';
                        $order_data['shipping_country'] = '';
                        $order_data['shipping_country_id'] = '';
                        $order_data['shipping_address_format'] = '';
                        $order_data['shipping_custom_field'] = array();
                        $order_data['shipping_method'] = '';
                        $order_data['shipping_code'] = '';
                    }

                    // Products
                    $order_data['products'] = array();

                    foreach ($this->cart->getProducts() as $product) {
                        $option_data = array();

                        foreach ($product['option'] as $option) {
                            $option_data[] = array(
                                'product_option_id' => $option['product_option_id'],
                                'product_option_value_id' => $option['product_option_value_id'],
                                'option_id' => $option['option_id'],
                                'option_value_id' => $option['option_value_id'],
                                'name' => $option['name'],
                                'value' => $option['value'],
                                'type' => $option['type']
                            );
                        }

                        $order_data['products'][] = array(
                            'product_id' => $product['product_id'],
                            'name' => $product['name'],
                            'model' => $product['model'],
                            'option' => $option_data,
                            'download' => $product['download'],
                            'quantity' => $product['quantity'],
                            'subtract' => $product['subtract'],
                            'price' => $product['price'],
                            'total' => $product['total'],
                            'tax' => $this->tax->getTax($product['price'], $product['tax_class_id']),
                            'reward' => $product['reward']
                        );
                    }

                    // Gift Voucher
                    $order_data['vouchers'] = array();

                    if (!empty($this->session->data['vouchers'])) {
                        foreach ($this->session->data['vouchers'] as $voucher) {
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

                    // Order Totals
                    $this->load->model('setting/extension');

                    $totals = array();
                    $taxes = $this->cart->getTaxes();
                    $total = 0;

                    // Because __call can not keep var references so we put them into an array.
                    $total_data = array(
                        'totals' => &$totals,
                        'taxes' => &$taxes,
                        'total' => &$total
                    );

                    $sort_order = array();

                    $results = $this->model_setting_extension->getExtensions('total');

                    foreach ($results as $key => $value) {
                        $sort_order[$key] = $this->config->get('total_' . $value['code'] . '_sort_order');
                    }

                    array_multisort($sort_order, SORT_ASC, $results);

                    foreach ($results as $result) {
                        if ($this->config->get('total_' . $result['code'] . '_status')) {
                            $this->load->model('extension/total/' . $result['code']);

                            // We have to put the totals in an array so that they pass by reference.
                            $this->{'model_extension_total_' . $result['code']}->getTotal($total_data);
                        }
                    }

                    $sort_order = array();

                    foreach ($total_data['totals'] as $key => $value) {
                        $sort_order[$key] = $value['sort_order'];
                    }

                    array_multisort($sort_order, SORT_ASC, $total_data['totals']);

                    $order_data = array_merge($order_data, $total_data);

                    if (isset($this->request->post['comment'])) {
                        $order_data['comment'] = $this->request->post['comment'];
                    } else {
                        $order_data['comment'] = '';
                    }

                    if (isset($this->request->post['affiliate_id'])) {
                        $subtotal = $this->cart->getSubTotal();

                        // Affiliate
                        $this->load->model('account/customer');

                        $affiliate_info = $this->model_account_customer->getAffiliate($this->request->post['affiliate_id']);

                        if ($affiliate_info) {
                            $order_data['affiliate_id'] = $affiliate_info['customer_id'];
                            $order_data['commission'] = ($subtotal / 100) * $affiliate_info['commission'];
                        } else {
                            $order_data['affiliate_id'] = 0;
                            $order_data['commission'] = 0;
                        }
                    } else {
                        $order_data['affiliate_id'] = 0;
                        $order_data['commission'] = 0;
                    }

                    $this->model_checkout_order->editOrder($order_id, $order_data);

                    // Set the order history
                    if (isset($this->request->post['order_status_id'])) {
                        $order_status_id = $this->request->post['order_status_id'];
                    } else {
                        $order_status_id = $this->config->get('config_order_status_id');
                    }

                    $this->model_checkout_order->addOrderHistory($order_id, $order_status_id);
                }
            } else {
                $json['error'] = $this->language->get('error_not_found');
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function delete()
    {
        $this->load->language('api/order');

        $json = array();

        if (!isset($this->session->data['api_id'])) {
            $json['error'] = $this->language->get('error_permission');
        } else {
            $this->load->model('checkout/order');

            if (isset($this->request->get['order_id'])) {
                $order_id = $this->request->get['order_id'];
            } else {
                $order_id = 0;
            }

            $order_info = $this->model_checkout_order->getOrder($order_id);

            if ($order_info) {
                $this->model_checkout_order->deleteOrder($order_id);

                $json['success'] = $this->language->get('text_success');
            } else {
                $json['error'] = $this->language->get('error_not_found');
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function info()
    {
        $this->load->language('api/order');

        $json = array();

        if (!isset($this->session->data['api_id'])) {
            $json['error'] = $this->language->get('error_permission');
        } else {
            $this->load->model('checkout/order');

            if (isset($this->request->get['order_id'])) {
                $order_id = $this->request->get['order_id'];
            } else {
                $order_id = 0;
            }

            $order_info = $this->model_checkout_order->getOrder($order_id);

            if ($order_info) {
                $json['order'] = $order_info;

                $json['success'] = $this->language->get('text_success');
            } else {
                $json['error'] = $this->language->get('error_not_found');
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function history()
    {
        $this->load->language('api/order');

        $json = array();

        if (!isset($this->session->data['api_id'])) {
            $json['error'] = $this->language->get('error_permission');
        } else {
            // Add keys for missing post vars
            $keys = array(
                'order_status_id',
                'notify',
                'override',
                'comment'
            );

            foreach ($keys as $key) {
                if (!isset($this->request->post[$key])) {
                    $this->request->post[$key] = '';
                }
            }

            $this->load->model('checkout/order');

            if (isset($this->request->get['order_id'])) {
                $order_id = $this->request->get['order_id'];
            } else {
                $order_id = 0;
            }

            $order_info = $this->model_checkout_order->getOrder($order_id);

            if ($order_info) {
                $this->model_checkout_order->addOrderHistory($order_id, $this->request->post['order_status_id'], $this->request->post['comment'], $this->request->post['notify'], $this->request->post['override']);

                $json['success'] = $this->language->get('text_success');
            } else {
                $json['error'] = $this->language->get('error_not_found');
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function processingCompleted()
    {
        // 获取订单ID
        $order_id = $this->request->post['orderId'];
        // 获取订单内容
        $orderData = $this->request->post['orderInfo'];
        $orderData = html_entity_decode($orderData, ENT_QUOTES);
        $orderData = json_decode($orderData, true);
        $this->load->model('checkout/order');
        $this->model_checkout_order->processingOrderCompleted($order_id, $orderData);
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($orderData));
    }

    private function removeAssociateAndComboInfo($salesOrderId)
    {
        Logger::app('start removeAssociateAndComboInfo,sales_order_id:' . $salesOrderId);
        $this->sales__model->removeAssociateAndComboInfo($salesOrderId);
        Logger::app('end removeAssociateAndComboInfo,sales_order_id:' . $salesOrderId);
    }

    /**
     * @Author xxl
     * @Description 自动购买创建费用单
     * @Date 15:21 2020/10/15
     * @Param ['api_id'=>****,'buyer_id'=>***,'sale_order_id'=>****]
     **/
    public function createFeeOrder()
    {
        $json = [];
        $buyer_id = $this->request->post('buyer_id', 0);
        if (!$buyer_id) {
            return $this->jsonFailed($this->language->get('error_customer'));
        }

        // tb_sys_customer_sales_order 主键
        $salesOrderIds = $this->request->post('sale_order_id', 0);
        if (!$salesOrderIds) {
            return $this->jsonFailed($this->language->get('error_sale_order'));
        }
        // 根据销售订单id，获取指定的仓租id,生成费用单
        $this->load->model('checkout/order');
        $orderAssociatedIds = $this->model_checkout_order->getAssociateIdBySalesOrderId($salesOrderIds);
        app(StorageFeeService::class)->bindByOrderAssociated($orderAssociatedIds);
        $salesInfo = app(StorageFeeRepository::class)->getBoundStorageFeeBySalesOrder([$salesOrderIds]);
        $feeOrderIdList = app(FeeOrderService::class)->createSalesFeeOrder($salesInfo);
        $feeOrderIdArr = array_values($feeOrderIdList);
        $json['fee_order_list'] = $feeOrderIdArr;
        return $this->jsonSuccess($json);
    }

    private function createOrderData($products, $total_data, $customer_info, $buyer_id, $paymentMethod, $paymentCode, $currency_info, $couponPerDiscount, $campaignPerDiscount)
    {
        $order_data = [
            'totals' => $total_data['totals'],
            'invoice_prefix' => $this->config->get('config_invoice_prefix'),
            'store_id' => $this->config->get('config_store_id'),
            'store_name' => $this->config->get('config_name'),
            'customer_id' => $customer_info['customer_id'],
            'customer_group_id' => $customer_info['customer_group_id'],
            'firstname' => $customer_info['firstname'],
            'lastname' => $customer_info['lastname'],
            'email' => $customer_info['email'],
            'telephone' => $customer_info['telephone'],
            'custom_field' => json_decode($customer_info['custom_field'], true)
        ];

        if ($order_data['store_id']) {
            $order_data['store_url'] = $this->config->get('config_url');
        } else {
            $order_data['store_url'] = request()->serverBag->get('HTTPS') ? HTTPS_SERVER : HTTP_SERVER;
        }

        $this->load->model('account/address');
        $paymentAddress = $this->model_account_address->getAddressWithBuyerId($customer_info['address_id'], $buyer_id);

        $order_data['payment_firstname'] = $paymentAddress['firstname'];
        $order_data['payment_lastname'] = $paymentAddress['lastname'];
        $order_data['payment_company'] = $paymentAddress['company'];
        $order_data['payment_address_1'] = $paymentAddress['address_1'];
        $order_data['payment_address_2'] = $paymentAddress['address_2'];
        $order_data['payment_city'] = $paymentAddress['city'];
        $order_data['payment_postcode'] = $paymentAddress['postcode'];
        $order_data['payment_zone'] = $paymentAddress['zone'];
        $order_data['payment_zone_id'] = $paymentAddress['zone_id'];
        $order_data['payment_country'] = $paymentAddress['country'];
        $order_data['payment_country_id'] = $paymentAddress['country_id'];
        $order_data['payment_address_format'] = $paymentAddress['address_format'];
        $order_data['payment_custom_field'] = (isset($paymentAddress['custom_field']) ? $paymentAddress['custom_field'] : array());

        $order_data['payment_method'] = $paymentMethod;
        $order_data['payment_code'] = $paymentCode;

        foreach ($products as $product) {
            $option_data = array();

            foreach ($product['option'] as $option) {
                $option_data[] = array(
                    'product_option_id' => $option['product_option_id'],
                    'product_option_value_id' => $option['product_option_value_id'],
                    'option_id' => $option['option_id'],
                    'option_value_id' => $option['option_value_id'],
                    'name' => $option['name'],
                    'value' => $option['value'],
                    'type' => $option['type']
                );
            }

            $order_data['products'][] = array(
                'product_id' => $product['product_id'],
                'name' => $product['name'],
                'model' => $product['model'],
                'option' => $option_data,
                'download' => $product['download'],
                'quantity' => $product['quantity'],
                'subtract' => $product['subtract'],
                //modified by xiesensen total重新计算为price*quantity
                'total' => round($product['price'], 2) * $product['quantity'],
                //end xiesensen
                'price' => round($product['product_price_per'], 2),//使用货值，即上门取货价
                'serviceFeePer' => round($product['service_fee_per'], 2),
                'serviceFee' => round($product['service_fee_per'], 2) * $product['quantity'],
                'tax' => $this->tax->getTax($product['price'], $product['tax_class_id']),
                'reward' => $product['reward'] ?? 0,
                //单件运费
                'freight_per' => $product['freight_per'],//基础运费+超重附加费
                'base_freight' => $product['base_freight'],//基础运费
                'overweight_surcharge' => $product['overweight_surcharge'],//超重附加费
                'coupon_amount' => $couponPerDiscount[$product['product_id']]['discount'] ?? 0, //优惠券金额
                'campaign_amount' => $campaignPerDiscount[$product['product_id']]['discount'] ?? 0, //满减金额
                'package_fee' => $product['package_fee_per'], //打包费
                'subProducts' => $product['sub_products'],
                'cart_id' => $product['cart_id'],
                'type_id' => $product['type_id'],
                'agreement_id' => $product['agreement_id'],
                'danger_flag' => $product['danger_flag'] ?? 0,
            );
        }

        if (!empty($this->session->data['vouchers'])) {
            foreach ($this->session->data['vouchers'] as $voucher) {
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

        if ($this->session->get('comment')) {
            $order_data['comment'] = $this->session->get('comment');
        } else {
            $order_data['comment'] = "";
        }
        $order_data['total'] = $total_data['total'];

        //默认为空
        $order_data['affiliate_id'] = 0;
        $order_data['commission'] = 0;
        $order_data['marketing_id'] = 0;
        $order_data['tracking'] = '';

        $order_data['language_id'] = $this->config->get('config_language_id');
        $order_data['currency_id'] = $currency_info['currency_id'];
        $order_data['currency_code'] = $currency_info['code'];
        //记录当前交易币种的汇率（美元是对美元的汇率，为1；英镑和欧元是对人民币的汇率，每日维护）
        $order_data['current_currency_value'] = $currency_info['value'];
        //addby xiesensen 汇率记为1
        /*$order_data['currency_value'] = $currency_info['value'];*/
        $order_data['currency_value'] = 1;
        $order_data['ip'] = request()->serverBag->get('REMOTE_ADDR');

        if (!empty($this->request->server['HTTP_X_FORWARDED_FOR'])) {
            $order_data['forwarded_ip'] = request()->serverBag->get('HTTP_X_FORWARDED_FOR');
        } elseif (!empty($this->request->server['HTTP_CLIENT_IP'])) {
            $order_data['forwarded_ip'] = $this->request->server['HTTP_CLIENT_IP'];
        } else {
            $order_data['forwarded_ip'] = '';
        }

        $order_data['user_agent'] = request()->serverBag->get('HTTP_USER_AGENT', '');

        $order_data['accept_language'] = request()->serverBag->get('HTTP_ACCEPT_LANGUAGE', '');
        return $order_data;
    }
}
