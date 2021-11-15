<?php

use App\Enums\Cart\CartAddCartType;
use App\Enums\Common\YesNoEnum;
use App\Enums\Product\ProductStatus;
use App\Enums\Product\ProductTransactionType;
use App\Models\Product\Product;
use App\Repositories\Futures\AgreementRepository as FuturesAgreementRepository;
use App\Repositories\Customer\CustomerRepository;

/**
 * Class ControllerCheckoutCart
 *
 * @property ModelCheckoutCart $model_checkout_cart
 * @property ModelAccountCartCart $model_account_cart_cart
 * @property ModelAccountCustomerpartner $model_account_customerpartner
 * @property ModelExtensionModulePrice $model_extension_module_price
 * @property ModelCheckoutSuccess $model_checkout_success
 * @property ModelCustomerpartnerMaster $model_customerpartner_master
 * @property ModelExtensionModuleCartHome model_extension_module_cart_home
 * @property ModelCommonProduct $model_common_product
 * @property ModelToolImage $model_tool_image
 * @property ModelFuturesAgreement $model_futures_agreement
 * @property ModelAccountSalesOrderMatchInventoryWindow $model_account_sales_order_match_inventory_window;
 * @property ModelCatalogProduct $model_catalog_product
 * @property ModelCheckoutPreOrder $model_checkout_pre_order
 * @property ModelCustomerPartnerDelicacyManagement $model_customerpartner_DelicacyManagement
 * @property ModelAccountProductQuoteswkproductquotes $model_account_product_quotes_wk_product_quotes
 */
class ControllerCheckoutCart extends Controller
{

    private $country_map = [
        'JPN'  => 107,
        'GBR'  => 222,
        'DEU'  => 81,
        'USA'  => 223
    ];

    public function __construct(Registry $registry)
    {
        parent::__construct($registry);

        if (!$this->customer->isLogged()) {
            session()->set('redirect', $this->url->link('checkout/cart', '', true));
            $this->response->redirect($this->url->link('account/login', '', true));
        }
    }

    public function index()
    {

        $this->load->language('checkout/cart');
        $this->load->model('checkout/cart');
        $this->load->model('account/customerpartner');

        $this->document->setTitle($this->language->get('heading_title'));

        $data['breadcrumbs'] = [
            [
                'href' => $this->url->link('common/home'),
                'text' => $this->language->get('text_home')
            ],
            [
                'href' => $this->url->link('checkout/cart'),
                'text' => $this->language->get('heading_title')
            ]
        ];
        $cart_model = $this->model_checkout_cart;
        $cart_model->updateCartProductStatus($this->customer->getId());

        //一件代发的购物车展示(一件代发+云送仓的tab),上门取货的buyer只展示上门取货的tab
        if ($this->customer->isCollectionFromDomicile()) {
            $data['isCollectionFromDomicile'] = true;
        } else {
            $data['isCollectionFromDomicile'] = false;
        }
        $data['has_cwf_freight'] = $this->customer->has_cwf_freight();

        //计算各tab的数量
        $data['drop_ship_qty'] = $this->cart->productsNum(0);
        $data['home_pick_qty'] = $this->cart->productsNum(1);
        $data['cloud_logistics_qty'] = $this->cart->productsNum(2);

        $data['products'] = [];
        if (0 == $data['home_pick_qty'] || !($data['drop_ship_qty'] && $data['cloud_logistics_qty'])){
            $this->load->model('checkout/success');
            $data['products'] = $this->model_checkout_success->recommendedProduct();
            $data['isLogin'] = customer()->isLogged();
        }

        if (isset($this->session->data['product_error'])) {//下单前校验上架库存、协议库存
            $data['error_warning'] = session('product_error');
            $this->session->remove('product_error');
        }else if (isset($this->session->data['error'])) {//下单锁库存时，批次库存不足
            $data['error_warning'] = session('error');
            $this->session->remove('error');
        }
        //下面注释掉的一段代码应该是没有用的，先试行一段时间，后期没问题再删除 2020/6/19 CL
/*        $product_quantity_restriction = $this->model_account_customerpartner->getProductRestriction();
        if ($this->config->get('module_marketplace_status') && $product_quantity_restriction) {
            $data['error_warning'] = sprintf($this->language->get('error_product_quantity_restriction'), (int)$this->config->get('marketplace_product_quantity_restriction'), $product_quantity_restriction['name']);
        }
        if ($this->config->get('module_marketplace_status') && (int)$this->config->get('marketplace_min_cart_value') && (int)$this->config->get('marketplace_min_cart_value') > $this->cart->getTotal()) {
            $data['error_warning'] = sprintf($this->language->get('error_min_cart_value'), $this->currency->format($this->config->get('marketplace_min_cart_value'), session('currency')));
        }*/

        if ($this->config->get('config_customer_price') && !$this->customer->isLogged()) {
            $data['attention'] = sprintf($this->language->get('text_login'), $this->url->link('account/login'), $this->url->link('account/register'));
        } else {
            $data['attention'] = '';
        }

        if ($this->session->get('success')) {
            $data['success'] = $this->session->get('success');
            $this->session->remove('success');
        } else {
            $data['success'] = '';
        }

        // 处理打开云送仓tab
        if ($this->session->get('show_cwf') || $this->request->get('show_cwf')) {
            $data['show_cwf'] = 1;
            $this->session->remove('show_cwf');
        } else {
            $data['show_cwf'] = 0;
        }
        $data['error_warning_final_cart'] = session('error_warning_final_cart', '');
        $this->session->remove('error_warning_final_cart');

        $data['column_left'] = $this->load->controller('common/column_left');
        $data['column_right'] = $this->load->controller('common/column_right');
        $data['content_top'] = $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');
        $data['footer'] = $this->load->controller('common/footer');
        $data['header'] = $this->load->controller('common/header');

        $this->response->setOutput($this->load->view('checkout/cart', $data));

    }

    public function add()
    {
        $this->load->language('account/product_quotes/wk_product_quotes');
        $this->load->language('checkout/cart');
        $this->load->model('customerpartner/DelicacyManagement');
        $this->load->model('catalog/product');
        $this->load->model('checkout/cart');
        $this->load->model('futures/agreement');
        $this->load->model('extension/module/cart_home');
        $this->load->model('account/product_quotes/wk_product_quotes');
        $this->load->model('customerpartner/master');

        $product_id = max($this->request->input->getInt('product_id'), 0);
        $quantity = max($this->request->input->getInt('quantity'), 1);
        $transaction_type = $this->request->input->get('transaction_type');
        $agreement_code = $this->request->input->get('agreement_code');
        $freight_radio = $this->request->input->get('freight_radio');
        $add_cart_type = $this->request->input->getInt('add_cart_type', CartAddCartType::DEFAULT_OR_OPTIMAL);
        $option = $this->request->input->get('option') ?? [];
        $recurring_id = max($this->request->input->getInt('recurring_id'), 0);
        // 为了区分详情页调用addCart,只校验reorder中的欧洲补运费产品
        $route = $this->request->post('route');
        if ($route == 'reorder') {
            $europe_freight_product_list = json_decode($this->config->get('europe_freight_product_id'));
            $country_id = Customer()->getCountryId();
            if (in_array($country_id, EUROPE_COUNTRY_ID)
                && in_array($product_id, $europe_freight_product_list)
            ) {
                $json['error']['transaction_type'] = $this->language->get('error_europe_product_limit');
                return $this->response->json($json);
            }
            // 产品不可用或seller已闭店
            /** @var Product $product */
            $product = Product::queryRead()
                ->with(['customerPartnerToProduct', 'customerPartnerToProduct.customer'])
                ->where('product_id', $product_id)
                ->first();
            if (empty($product) || $product->status != ProductStatus::ON_SALE || $product->buyer_flag != YesNoEnum::YES || $product->customerPartnerToProduct->customer->status != YesNoEnum::YES) {
                $json['error']['msg'] = 'The product is unavailable.';
                return $this->response->json($json);
            }
        }

        if (!is_null($freight_radio) && $freight_radio == 'cwf') {
            $delivery_type = 2;
        } else {
            $delivery_type = null;
        }
        // 添加校验
        $unsupportStockMap = app(CustomerRepository::class)->getUnsupportStockData([$product_id]);
        if(in_array($product_id,$unsupportStockMap)){
            $json['error']['transaction_type'] = $this->language->get('error_unsupport_stock_limit');
            return $this->response->json($json);
        }

        // 购物车上限校验
        if (!$this->checkCartCount($delivery_type)) {
            $json['error']['transaction_type'] = $this->language->get('error_limit_cart');
            return $this->response->setOutput(json_encode($json));
        }

        //添加云送仓
        if (!is_null($freight_radio)) {
            if ($freight_radio == 'cwf') {
                $freight = 2;
            } else {
                if ($this->customer->isCollectionFromDomicile()) {
                    $freight = 1;
                } else {
                    $freight = 0;
                }
            }
        } else {
            if ($this->customer->isCollectionFromDomicile()) {
                $freight = 1;
            } else {
                $freight = 0;
            }
        }

        $cart_model = $this->model_checkout_cart;
        $json = array();

        $sellerInfo = $this->model_customerpartner_master->getInfoByProductId($product_id);
        if (!$sellerInfo || $sellerInfo['status'] == 0) {
            $json['error']['seller'] = $this->language->get('error_seller');
            goto end;
        }

        //检验是否精细化不能购买
        $visible = true;
        $result = $this->model_customerpartner_DelicacyManagement->getProductPrice($product_id, $this->customer->getId());
        if (empty($result)) {
            $visible = false;
            $json['error']['transaction_type'] = $this->language->get('error_add_cart');
        }
        $product_info = $this->model_catalog_product->getProduct($product_id);
        if ($product_info) {
            $agreement_id = null;
            $type = 0;
            //195_2 agreement_id  k price_value price_all type qty
            if (!is_null($transaction_type)) {
                if ($transaction_type != 0) {
                    $info = explode('_', $transaction_type);
                    $agreement_id = $info[0];
                    //验证协议是否失效
                    $type = $info[1];
                    $transaction_info = $this->cart->getTransactionTypeInfo($type, $agreement_id, $product_id);
                    if ($this->config->get('common_performer_type_margin_spot') == $type) {
                        if (!$transaction_info) {
                            // 获取agreement id
                            $agreement_code = $cart_model->getTransactionTypeInfo($type, $agreement_id, $product_id);
                            $json['error']['transaction_type'] = sprintf($this->language->get('error_expire_time_add_cart'), $agreement_code);
                        }
                    } elseif ($this->config->get('common_performer_type_margin_futures') == $type
                        && 6 != $transaction_info['delivery_status']) {
                        $json['error']['transaction_type'] = sprintf($this->language->get('error_expire_time_add_cart'),
                            $transaction_info['agreement_no']);
                    }
                    //现货保证金协议 过期
                    if ($type == ProductTransactionType::MARGIN && $transaction_info) {
                        if ($transaction_info['expire_time'] < date('Y-m-d H:i:s')) {
                            $json['error']['transaction_type'] = sprintf($this->language->get('error_expire_time_add_cart'), $transaction_info['agreement_code']);
                            goto end;
                        }
                    }

                    if ($type == ProductTransactionType::FUTURE && $transaction_info && $transaction_info['version'] == 3) {
                        $apiId = ($this->session->has('api_id') ? $this->session->get('api_id') : 0);
                        //如果购物车已存在相同的 期货协议三期，那么不允许再次添加
                        if (app(FuturesAgreementRepository::class)->cartExistFuturesAgreement($this->customer->getId(), $product_id, $agreement_id, $freight, $apiId)) {
                            $json['error']['cartExistFuturesAgreement'] = '1';
                            $json['error']['transaction_type'] = $this->language->get('error_futures_transaction_add_cart_exist');
                            goto end;
                        }
                        $quantity = $transaction_info['left_qty'] ?? $quantity;//如果添加商品是期货期货三期，那么添加至购物车的数量为期货协议产品的全部数量
                    }

                    //购物车已存在相同的 议价协议，则不允许再次添加
                    if ($visible && $type == ProductTransactionType::SPOT) {
                        if ($this->model_account_product_quotes_wk_product_quotes->cartExistSpotAgreement($this->customer->getId(), $product_id, $agreement_id, $freight)) {
                            $json['error']['transaction_type'] = $this->language->get('error_transaction_add_cart_exist');
                            goto end;
                        }
                    }
                } else {
                    //验证是否是保证金头款
                    $map = [
                        'process_status' => 1,
                        'advance_product_id' => $product_id,
                    ];
                    $agreement_id = $this->orm->table('tb_sys_margin_process')->where($map)->value('margin_id');
                    if ($agreement_id) {
                        $type = ProductTransactionType::MARGIN;
                    } else {//验证是不是期货头款
                        $agreement_id = $this->model_futures_agreement->getFuturesIdByAdvanceProductId($product_id);
                        $type = $agreement_id ? ProductTransactionType::FUTURE : $type;
                    }
                }
            } else {
                $map = [
                    'process_status' => 1,
                    'advance_product_id' => $product_id,
                ];
                $agreement_id = $this->orm->table('tb_sys_margin_process')->where($map)->value('margin_id');
                if ($agreement_id) {
                    $type = ProductTransactionType::MARGIN;
                }
            }

            //新增了逻辑相同产品的不同交易方式不允许同时添加购物车
            $count = $cart_model->verifyProductAdd($product_id, $type, $agreement_id, $delivery_type);

            if ($count) {
                $json['error']['transaction_type'] = $this->language->get('error_transaction_add_cart');
            }
            $product_options = $this->model_catalog_product->getProductOptions($product_id);
            foreach ($product_options as $product_option) {
                if ($product_option['required'] && empty($option[$product_option['product_option_id']])) {
                    $json['error']['option'][$product_option['product_option_id']] = sprintf($this->language->get('error_required'), $product_option['name']);
                }
            }

            if (!$json) {
                //$this->cart->add($this->request->post['product_id'], $quantity, $option, $recurring_id);
                $json['cart_id'] = $cart_model->add($product_id, $quantity, $option, $recurring_id, $type, $agreement_id, $freight, $add_cart_type);
                if ($this->customer->isCollectionFromDomicile()) {
                    $freight_txt = 'Pick Up';
                } else {
                    if ($freight == 2) {
                        $freight_txt = 'Cloud Wholesale Fulfillment';
                    } else {
                        $freight_txt = 'Drop Shipping';
                    }
                }
                $cartUrl = url(['checkout/cart']);
                if ($freight_radio == 'cwf') {
                    $cartUrl .= "&show_cwf=1";
                }
                $json['success'] = sprintf($this->language->get('text_success_add_cart'),
                    $this->url->link('product/product', ['product_id' => $product_id]),
                    $product_info['sku'],
                    $cartUrl,
                    $freight_txt);
                // Unset all shipping and payment methods
                $this->session->remove('shipping_method');
                $this->session->remove('shipping_methods');
                $this->session->remove('payment_method');
                $this->session->remove('payment_methods');

                // Totals
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

                // Display prices
                if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
                    $sort_order = array();

                    $results = $this->model_setting_extension->getExtensions('total');
                    foreach ($results as $key => $value) {
                        $sort_order[$key] = $this->config->get('total_' . $value['code'] . '_sort_order');
                    }
                    foreach ($results as $result) {
                        if ($this->config->get('total_' . $result['code'] . '_status')) {
                            $this->load->model('extension/total/' . $result['code']);
                            //sub_total -> getProducts
                            // We have to put the totals in an array so that they pass by reference.
                            $this->{'model_extension_total_' . $result['code']}->getTotal($total_data);
                        }
                    }
                    $sort_order = array();

                    foreach ($totals as $key => $value) {
                        $sort_order[$key] = $value['sort_order'];
                    }

                    array_multisort($sort_order, SORT_ASC, $totals);
                }
                $carts_info = $this->model_extension_module_cart_home->getCartInfo($this->customer->getId());
                $totalMoney = $this->currency->format($carts_info['total_price'], $this->session->get('currency'));
                $json['totalNum'] = $carts_info['quantity'];
                $json['totalMoney'] = $totalMoney;
                $json['total'] = sprintf($this->language->get('text_items'), $json['totalNum'], $totalMoney);
            } else {
                $json['redirect'] = $this->url->to(['product/product', 'product_id' => $product_id]);
            }
        }

        end:

        return $this->response->json($json);
    }

    /**
     *购物车上限校验
     * @param $delivery_type
     *          drop shipping 0
     *          home pick 1
     *          cwf 2
     * @return bool
     */
    protected function checkCartCount($delivery_type)
    {
        if (is_null($delivery_type)) {
            // 验证是否是上门取货的buyer,上门取货默认加入home pick
            $delivery_type = $this->customer->isCollectionFromDomicile() ? 1 : 0;
        }
        $count = $this->cart->productsNum($delivery_type);
        if ($count >= $this->config->get('cart_limit')) {
            return false;
        }
        return true;
    }

    public function edit()
    {
        $this->load->language('checkout/cart');
        $this->load->model('checkout/cart');

        $cart_model = $this->model_checkout_cart;

        $json = array();
        // Update
        if (!empty($this->request->post['quantity'])) {
            foreach ($this->request->post['quantity'] as $key => $value) {
                if (isset($this->request->post['transaction_type_' . $key])) {
                    $transaction_type = $this->request->post['transaction_type_' . $key];
                } else {
                    $transaction_type = 0; //普通的格式
                }

                $cart_model->updateCart($key, $value, $transaction_type);
                //$this->cart->update($key, $value);
            }

//            session()->set('success', $this->language->get('text_remove'));

            $this->session->remove('shipping_method');
            $this->session->remove('shipping_methods');
            $this->session->remove('payment_method');
            $this->session->remove('payment_methods');
            $this->session->remove('reward');
            if ($this->request->get('delivery_type')) {
                $this->session->set('show_cwf', 1);
            }
            return $this->redirect(url(['checkout/cart']));
        }

        return $this->json($json);
    }

    public function remove()
    {
        $this->load->language('checkout/cart');
        $this->load->model('extension/module/cart_home');
        $json = array();

        // Remove
        if (isset($this->request->post['key'])) {
            if (!empty($this->request->post['delivery_type'])) {
                $this->cart->remove($this->request->post['key'], $this->request->post['delivery_type']);
            } else {
                if ($this->customer->isCollectionFromDomicile()) {
                    $this->cart->remove($this->request->post['key'], 1);
                } else {
                    $this->cart->remove($this->request->post['key']);
                }
            }

            $json['success'] = $this->language->get('text_remove');

            $this->session->removeDeepByKey('vouchers', $this->request->post['key']);
            $this->session->remove('shipping_method');
            $this->session->remove('shipping_methods');
            $this->session->remove('payment_method');
            $this->session->remove('payment_methods');
            $this->session->remove('reward');

            $carts_info = $this->model_extension_module_cart_home->getCartInfo($this->customer->getId());
            $totalMoney = $this->currency->format( $carts_info['total_price'], session('currency'));
            $json['totalNum'] = $carts_info['quantity'];
            $json['totalMoney'] = $totalMoney;
            $json['total'] = sprintf($this->language->get('text_items'), $json['totalNum'], $totalMoney);

            $json['drop_ship_qty'] = $this->cart->productsNum(0);
            $json['home_pick_qty'] = $this->cart->productsNum(1);
            $json['cloud_logistics_qty'] = $this->cart->productsNum(2);
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function cart_drop_ship()
    {
        $this->load->language('checkout/cart');
        if ($this->cart->hasProducts(0) || !empty($this->session->data['vouchers'])) {
            $this->load->model('account/cart/cart');
            $this->load->model('account/customerpartner');
            $this->load->model('setting/extension');

            $cartModel = $this->model_account_cart_cart;
            //一件代发的delivery_type = 0
            $delivery_type = 0;
            session()->set('delivery_type', $delivery_type);
            $data = $cartModel->cartShowByStore($delivery_type);//print_r($data);die;
            $data['delivery_type'] = $delivery_type;
            $data['num'] = $this->cart->productsNum($delivery_type);
            if (!$this->cart->hasStock()
                && (!$this->config->get('config_stock_checkout') || $this->config->get('config_stock_warning'))) {
                $data['error_warning'] = $this->language->get('error_stock');
            } elseif (isset($this->session->data['error'])) {
                $data['error_warning'] = session('error');
                $this->session->remove('error');
            } else {
                $data['error_warning'] = '';
            }
            $product_quantity_restriction = $this->model_account_customerpartner->getProductRestriction();
            if ($this->config->get('module_marketplace_status') && $product_quantity_restriction) {
                $data['error_warning'] = sprintf($this->language->get('error_product_quantity_restriction'),
                    (int)$this->config->get('marketplace_product_quantity_restriction'),
                    $product_quantity_restriction['name']);
            }

            if ($this->config->get('module_marketplace_status')
                && (int)$this->config->get('marketplace_min_cart_value')
                && (int)$this->config->get('marketplace_min_cart_value') > $this->cart->getTotal()) {
                $data['error_warning'] = sprintf($this->language->get('error_min_cart_value'),
                    $this->currency->format($this->config->get('marketplace_min_cart_value'), session('currency')));
            }

            if ($this->config->get('config_customer_price') && !$this->customer->isLogged()) {
                $data['attention'] = sprintf($this->language->get('text_login'), $this->url->link('account/login'), $this->url->link('account/register'));
            } else {
                $data['attention'] = '';
            }

            if (isset($this->session->data['success'])) {
                $data['success'] = session('success');
                $this->session->remove('success');
            } else {
                $data['success'] = '';
            }

            if ($this->config->get('config_cart_weight')) {
                $data['weight'] = $this->weight->format($this->cart->getWeight(), $this->config->get('config_weight_class_id'), $this->language->get('decimal_point'), $this->language->get('thousand_point'));
            } else {
                $data['weight'] = '';
            }

            $data['img_vat_url'] = ($this->request->server['HTTPS'] ? $this->config->get('config_ssl') : $this->config->get('config_url')) . 'image/product/vat.png';

            //上门取货buyer
            $data['isCollectionFromDomicile'] = $this->customer->isCollectionFromDomicile();
            $data['has_cwf_freight'] = $this->customer->has_cwf_freight();
            //测试店铺、服务店铺、保证金店铺的产品、期货/现货保证金定金类产品在购物车中不显示转移到云送仓购物车的按钮
            if ($this->config->get('module_marketplace_status')) {
                $data['module_marketplace_status'] = $this->config->get('module_marketplace_status');
                if (isset($this->session->data['sellerProducts'])) {
                    $data['error_warning_seller_product'] = " Warning: Please remove " . trim(session('sellerProducts'), ', ') . " from cart to checkout!";
                    $this->session->remove('sellerProducts');
                } else {
                    $data['error_warning_seller_product'] = false;
                }
            }

            $data['innerAutoBuyAttr1'] = $this->customer->innerAutoBuyAttr1();//内部自动购买采销异体账号

            $data['action'] = $this->url->link('checkout/cart/edit', '', true);
            $data['checkInnerAutoBuyUrl'] = $this->url->link('checkout/cart/checkInnerAutoBuy', '', true);
            $data['checkDropShipCartUrl'] = $this->url->link('checkout/cart/checkDropShipCart');
            $data['preOrderUrl'] = $this->url->link('checkout/pre_order');

            $this->response->setOutput($this->load->view('checkout/cart_drop_ship', $data));
        }else{
            $this->session->remove('success');

            $data['text_error'] = $this->language->get('text_empty');
            $data['continue'] = $this->url->link('common/home');
            $data['app_version'] = APP_VERSION;
            $this->response->setOutput($this->load->view('checkout/empty_cart', $data));
        }

    }

    public function cart_cloud_logistics()
    {
        $this->load->language('checkout/cart');
        //云送仓的delivery_type = 2
        $delivery_type = 2;
        if ($this->cart->hasProducts($delivery_type)) {
            $this->load->model('account/cart/cart');
            $this->load->model('account/customerpartner');
            $this->load->model('setting/extension');
            $this->load->model('account/customer_order');

            $cartModel = $this->model_account_cart_cart;

            session()->set('delivery_type', $delivery_type);
            $data = $cartModel->cartShowByStore($delivery_type);
            $data['delivery_type'] = $delivery_type;
            $data['num'] = $this->cart->productsNum($delivery_type);

            if ($this->config->get('config_customer_price') && !$this->customer->isLogged()) {
                $data['attention'] = sprintf($this->language->get('text_login'), $this->url->link('account/login'), $this->url->link('account/register'));
            } else {
                $data['attention'] = '';
            }

            if (isset($this->session->data['success'])) {
                $data['success'] = session('success');
                $this->session->remove('success');
            } else {
                $data['success'] = '';
            }

            if ($this->config->get('config_cart_weight')) {
                $data['weight'] = $this->weight->format($this->cart->getWeight(), $this->config->get('config_weight_class_id'), $this->language->get('decimal_point'), $this->language->get('thousand_point'));
            } else {
                $data['weight'] = '';
            }

            $data['img_vat_url'] = ($this->request->server['HTTPS'] ? $this->config->get('config_ssl') : $this->config->get('config_url'))
                . 'image/product/vat.png';

            //上门取货buyer
            $data['isCollectionFromDomicile'] = $this->customer->isCollectionFromDomicile();
            $data['has_cwf_freight'] = $this->customer->has_cwf_freight();
            if ($this->config->get('module_marketplace_status')) {
                $data['module_marketplace_status'] = $this->config->get('module_marketplace_status');
                if (isset($this->session->data['sellerProducts'])) {
                    $data['error_warning_seller_product'] = " Warning: Please remove " . trim(session('sellerProducts'), ', ') . " from cart to checkout!";
                    $this->session->remove('sellerProducts');
                } else {
                    $data['error_warning_seller_product'] = false;
                }
            }

            $data['freight_rate'] =  $this->config->get('cwf_base_cloud_freight_rate');//考虑废弃
            $data['innerAutoBuyAttr1'] = $this->customer->innerAutoBuyAttr1();//内部自动购买采销异体账号

            $data['action'] = $this->url->link('checkout/cart/edit&delivery_type='.$delivery_type, false);
            $data['checkInnerAutoBuyUrl'] = $this->url->link('checkout/cart/checkInnerAutoBuy', '', true);
            $data['checkCwfCartUrl'] = $this->url->link('checkout/cart/checkCwfCart');

            //1363 云送仓增加超重附加费
            $data['cwf_overweight_surcharge_rate'] = ($this->config->get('cwf_overweight_surcharge_rate') * 100) . '%';//超重附加费费率
            $data['cwf_overweight_surcharge_min_weight'] = $this->config->get('cwf_overweight_surcharge_min_weight');//超重附加费最低单位体积
            if ($this->customer->isPartner()) {
                $data['cwf_info_id'] = $this->config->get('cwf_help_id');
            } else {
                $data['cwf_info_id'] = $this->config->get('cwf_help_information_id');
            }

            $this->session->remove('delivery_type');
            $this->response->setOutput($this->load->view('checkout/cart_cloud_logistics', $data));
        }else{
            $this->session->remove('success');

            $data['text_error'] = $this->language->get('text_empty');
            $data['continue'] = $this->url->link('common/home');
            $data['app_version'] = APP_VERSION;
            $this->response->setOutput($this->load->view('checkout/empty_cart', $data));
        }
    }

    public function cart_home_pick()
    {
        $this->load->language('checkout/cart');
        if ($this->cart->hasProducts(1) || !empty($this->session->data['vouchers'])) {
            $this->load->model('account/cart/cart');

            $cartModel = $this->model_account_cart_cart;
            //上门取货的delivery_type = 1
            $delivery_type = 1;
            session()->set('delivery_type', $delivery_type);
            $data = $cartModel->cartShowByStore($delivery_type);
            $data['delivery_type'] = 1;
            $data['num'] = $this->cart->productsNum($delivery_type);

            if (!$this->cart->hasStock() && (!$this->config->get('config_stock_checkout') || $this->config->get('config_stock_warning'))) {
                $data['error_warning'] = $this->language->get('error_stock');
            } elseif (isset($this->session->data['error'])) {
                $data['error_warning'] = session('error');
                $this->session->remove('error');
            } else {
                $data['error_warning'] = '';
            }
            $this->load->model('account/customerpartner');
            $product_quantity_restriction = $this->model_account_customerpartner->getProductRestriction();
            if ($this->config->get('module_marketplace_status') && $product_quantity_restriction) {
                $data['error_warning'] = sprintf($this->language->get('error_product_quantity_restriction'), (int)$this->config->get('marketplace_product_quantity_restriction'), $product_quantity_restriction['name']);
            }

            if ($this->config->get('module_marketplace_status') && (int)$this->config->get('marketplace_min_cart_value') && (int)$this->config->get('marketplace_min_cart_value') > $this->cart->getTotal()) {
                $data['error_warning'] = sprintf($this->language->get('error_min_cart_value'), $this->currency->format($this->config->get('marketplace_min_cart_value'), session('currency')));
            }

            if ($this->config->get('config_customer_price') && !$this->customer->isLogged()) {
                $data['attention'] = sprintf($this->language->get('text_login'), $this->url->link('account/login'), $this->url->link('account/register'));
            } else {
                $data['attention'] = '';
            }

            if (isset($this->session->data['success'])) {
                $data['success'] = session('success');
                $this->session->remove('success');
            } else {
                $data['success'] = '';
            }

            if ($this->config->get('config_cart_weight')) {
                $data['weight'] = $this->weight->format($this->cart->getWeight(), $this->config->get('config_weight_class_id'), $this->language->get('decimal_point'), $this->language->get('thousand_point'));
            } else {
                $data['weight'] = '';
            }

            $this->load->model('setting/extension');

            $data['img_vat_url'] = ($this->request->server['HTTPS'] ? $this->config->get('config_ssl') : $this->config->get('config_url'))
                . 'image/product/vat.png';
            //end xxl

            //N-63 B2B页面改版
            $totalNum = $this->cart->countProducts() + count(session('vouchers', []));
            $data['totalNum'] = $totalNum;
            $data['allItemNum'] = isset($data['productsCount']) ? $data['productsCount'] : 0;
            //上门取货buyer
            $data['isCollectionFromDomicile'] = $this->customer->isCollectionFromDomicile();

            if ($this->config->get('module_marketplace_status')) {
                $data['module_marketplace_status'] = $this->config->get('module_marketplace_status');
                if (isset($this->session->data['sellerProducts'])) {
                    $data['error_warning_seller_product'] = " Warning: Please remove " . trim(session('sellerProducts'), ', ') . " from cart to checkout!";
                    $this->session->remove('sellerProducts');
                } else {
                    $data['error_warning_seller_product'] = false;
                }
            }
            $this->session->remove('delivery_type');
            $data['innerAutoBuyAttr1'] = $this->customer->innerAutoBuyAttr1();//内部自动购买采销异体账号

            $data['action'] = $this->url->link('checkout/cart/edit', '', true);
            $data['checkInnerAutoBuyUrl'] = $this->url->link('checkout/cart/checkInnerAutoBuy', '', true);
            $data['checkPickUpCartUrl'] = $this->url->link('checkout/cart/checkPickUpCart');
            $data['preOrderUrl'] = $this->url->link('checkout/pre_order');
            $this->response->setOutput($this->load->view('checkout/cart_home_pick', $data));
        }else{
            $this->session->remove('success');

            $data['text_error'] = $this->language->get('text_empty');
            $data['continue'] = $this->url->link('common/home');
            $data['app_version'] = APP_VERSION;
            $this->response->setOutput($this->load->view('checkout/empty_cart', $data));
        }
    }

    public function freightDetailsShow()
    {
        $this->load->language('checkout/cart');
        $this->load->model('common/product');

        $product_model = $this->model_common_product;
        $this->load->model('tool/image');
        $imageModel = $this->model_tool_image;
        $productInfo = [];
        //查询云送仓的购物车的产品
        $cartIdStr = get_value_or_default($this->request->get, 'cart_id_str', '');
        $cartIdArr = explode(',', $cartIdStr);
        $results = $this->cart->getProducts(null, 2,$cartIdArr);
        $productIdArr = array();
        $total = array();
        foreach ($results as $product) {
            array_push($productIdArr, $product['product_id']);
        }
        $freightAndPackageFeeArr = $this->freight->getFreightAndPackageFeeByProducts($productIdArr);
        /**
         * Total Packages:总包裹数
         * Total Volume (m³):总体积数
         * Total Shipping Fee (USD):总的云送仓运费
         * Total Packaging Fee (USD):总的打包费
         * Total Freight (USD):云送仓物流报价 (USD / m³) * Total Volume (m³)（小于2的按2算） + Packaging Fee (USD / package) * Total Packages
         */
        $totalPackage = 0;
        $totalVolume = 0;
        $shippingRate = $this->config->get('cwf_base_cloud_freight_rate');
        $totalPackageFee = 0;
        foreach ($results as $product) {

            //产品的tag标签
            $this->load->model('catalog/product');
            $tag_array = $this->model_catalog_product->getTag($product['product_id']);
            $tags = array();
            if(isset($tag_array)){
                foreach ($tag_array as $tag){
                    if(isset($tag['origin_icon']) && !empty($tag['origin_icon'])){
                        //采用唯一图片  LTL.jpg  part.jpg  combo-img.png
                        $img_url = $this->model_tool_image->getOriginImageProductTags($tag['origin_icon']);
                        $tags[] = '<img data-toggle="tooltip" class="'.$tag['class_style']. '"    title="'.$tag['description']. '" style="padding-left: 1px" src="'.$img_url.'">';
                    }
                }
            }

            if ($product['combo'] == 1) {
                //combo产品运费展示
                foreach ($freightAndPackageFeeArr[$product['product_id']] as $set_product_id => $freightAndPackage) {
                    $shippingRate = $freightAndPackage['freight_rate'];
                    $setProductInfo = $product_model->getComboProductBySetProductId($product['product_id'], $set_product_id);
                    $setProductQty = $setProductInfo[0]['qty'] * $product['quantity'];
                    $freightAndPackageFeeArr[$product['product_id']][$set_product_id]['qty'] = $setProductQty;
                    $totalPackage += $setProductQty;
                    $totalVolume += $freightAndPackage['volume_inch'] * $setProductQty;//102497 换成立方英尺
                    $totalPackageFee += $freightAndPackage['package_fee'] * $setProductQty;
                }
            } else {
                $shippingRate = $freightAndPackageFeeArr[$product['product_id']]['freight_rate'];
                $totalPackage += $product['quantity'];
                $totalVolume += $freightAndPackageFeeArr[$product['product_id']]['volume_inch'] * $product['quantity'];//102497 换成立方英尺
                $totalPackageFee += $freightAndPackageFeeArr[$product['product_id']]['package_fee'] * $product['quantity'];

            }
            if ($product['image']) {
                $image = $imageModel->resize($product['image'], $this->config->get('theme_' . $this->config->get('config_theme') . '_image_cart_width'), $this->config->get('theme_' . $this->config->get('config_theme') . '_image_cart_height'));
            } else {
                $image = $imageModel->resize('no_image.png', $this->config->get('theme_' . $this->config->get('config_theme') . '_image_cart_width'), $this->config->get('theme_' . $this->config->get('config_theme') . '_image_cart_height'));
            }
            if (!$image) {
                $image = $imageModel->resize('no_image.png', $this->config->get('theme_' . $this->config->get('config_theme') . '_image_cart_width'), $this->config->get('theme_' . $this->config->get('config_theme') . '_image_cart_height'));
            }
            $productInfo[] = array(
                'thumb' => $image,
                'name' => $product['name'],
                'href' => $this->url->link('product/product', 'product_id=' . $product['product_id']),
                'item_code' => $product['sku'],
                'store' => $product['screenname'],
                'qty' => $product['quantity'],
                'weight' => $product['combo'] == 1 ? null : $freightAndPackageFeeArr[$product['product_id']]['weight'],
                'length' => $product['combo'] == 1 ? null : $freightAndPackageFeeArr[$product['product_id']]['length_inch'],
                'width' => $product['combo'] == 1 ? null : $freightAndPackageFeeArr[$product['product_id']]['width_inch'],
                'height' => $product['combo'] == 1 ? null : $freightAndPackageFeeArr[$product['product_id']]['height_inch'],
                'volume' => $product['combo'] == 1 ? null : $freightAndPackageFeeArr[$product['product_id']]['volume_inch'],//102497 换成立方英尺
                'setProductInfos' => $product['combo'] == 1 ? $freightAndPackageFeeArr[$product['product_id']] : null,
                'comoboFlag' => $product['combo'] == 1 ? true : false,
                'tag' => $tags
            );
        }
        $totalShipingFee = (double)($totalVolume * $shippingRate);
        $totalFreight = (double)($totalShipingFee + $totalPackageFee);
        $total[] = array(
            'title' => 'Total Packages',
            'text' => $totalPackage
        );
        $total[] = array(
            'title' => 'Total Volume(' . $this->language->get('volume_class') . ')',
            'text' => $totalVolume
        );
        $total[] = array(
            'title' => 'Total Shipping Fee(USD)',
            'text' => $totalShipingFee
        );
        $total[] = array(
            'title' => 'Total Packaging Fee(USD)',
            'text' => $totalPackageFee
        );
        $total[] = array(
            'title' => 'Total Freight(USD)',
            'text' => $totalFreight
        );
        $data['freightDetails'] = $productInfo;
        $data['totals'] = $total;
        $data['lowerVolume'] = CLOUD_LOGISTICS_VOLUME_LOWER;
        $data['freightRate'] = $shippingRate;
        $data['show_notice'] = bccomp($totalVolume, CLOUD_LOGISTICS_VOLUME_LOWER) === -1 ? true : false;
        $data['cwf_href'] = $this->url->link('information/information&information_id='.$this->config->get('cwf_help_id'), '', true);
        $data['cloud_freight_rate'] = $this->config->get('cwf_base_cloud_freight_rate');
        $this->response->setOutput($this->load->view('checkout/freight_details', $data));
    }

    public function checkDropShipCart()
    {
        //language
        $this->load->language('checkout/cwf_info');
        $this->load->model('checkout/cwf_info');
        $this->load->model('extension/module/price');
        $this->load->model('futures/agreement');
        $this->load->model('account/product_quotes/margin');

        $cartIdStr = get_value_or_default($this->request->post, 'cart_id_str', '');
        $deliveryType = get_value_or_default($this->request->post, 'delivery_type', 1);
        $qtyTypes = $this->request->post('qty_types', []);
        $cartIdArr = explode(',', $cartIdStr);
        $runId = get_value_or_default($this->request->get, 'run_id', 0);
        $this->model = $this->model_checkout_cwf_info;
        $cart_info = $this->cart->getProducts(null, $deliveryType, $cartIdArr);
        $cart_info = array_combine(array_column($cart_info, 'cart_id'), $cart_info);
        $this->load->model('customerpartner/DelicacyManagement');
        $delicacy_model = $this->model_customerpartner_DelicacyManagement;
        $delicacy_info = $delicacy_model->checkIsDisplay_batch(array_column($cart_info, 'product_id'), $this->customer->getId());
        $quantity_list = $this->model->get_quantity(array_column($cart_info, 'product_id'));

        $cartIds = array_column($cart_info, 'cart_id');
        $selectCartInfo = [];
        foreach ($cart_info as $k => $v) {
            //是否为保证金头款商品
            $isMarginAdvanceProduct = $this->model_account_product_quotes_margin->isMarginAdvanceProduct($v['product_id']);

            //是否为期货头款商品 N-294
            $isFuturesAdvanceProduct = $this->model_futures_agreement->isFuturesAdvanceProduct($v['product_id']);

            $priceModel = $this->model_extension_module_price;
            $transactionQtyResults = $this->model_extension_module_price->getProductPriceInfo($v['product_id'], $this->customer->getId(), [], false, true, ['qty' => $v['quantity']]);
            //普通交易形式
            if($v['type_id'] == ProductTransactionType::NORMAL){
                if (!empty($qtyTypes[$v['cart_id']])) {
                    if ($qtyTypes[$v['cart_id']] == 'Promotional' && isset($transactionQtyResults['base_info']['time_limit_buy'])) {
                        $transactionQtyResults['base_info']['quantity'] = $transactionQtyResults['base_info']['time_limit_qty'];
                    }
                }
                $quantity = $transactionQtyResults['base_info']['quantity'];
            }elseif ($v['type_id'] == ProductTransactionType::REBATE){
                $quantity = $transactionQtyResults['base_info']['quantity'];
            }elseif ($v['type_id'] == ProductTransactionType::MARGIN){
                foreach ($transactionQtyResults['transaction_type'] as $transactionQty){
                    if($transactionQty['type'] == ProductTransactionType::MARGIN && $v['agreement_code'] == $transactionQty['agreement_code']){
                        $quantity = $transactionQty['left_qty'];
                        break;
                    }
                }
            }elseif ($v['type_id'] == ProductTransactionType::FUTURE){
                foreach ($transactionQtyResults['transaction_type'] as $transactionQty){
                    if($transactionQty['type'] == ProductTransactionType::FUTURE && $v['agreement_code'] == $transactionQty['agreement_code']){
                        $quantity = $transactionQty['left_qty'];
                        break;
                    }
                }
            }elseif ($v['type_id'] == ProductTransactionType::SPOT){
                foreach ($transactionQtyResults['transaction_type'] as $transactionQty){
                    if($transactionQty['type'] == ProductTransactionType::SPOT && $v['agreement_code'] == $transactionQty['agreement_code']){
                        if ($transactionQty['qty'] != $v['quantity']) {
                            $json['success'] = false;
                            $json['msg'] = $this->language->get('check_spot_agreement_error_quantity');
                            break 2;
                        }
                        $quantity = $transactionQty['left_qty'];
                        break;
                    }
                }
            }

            //上架且可独立售卖且Buyer可见
            if (!in_array($v['product_id'], $delicacy_info) || !isset($v['quantity'])) {
                $json['success'] = false;
                $json['msg'] = sprintf($this->language->get('check_error_available'), $v['sku']);
                break;
            }
            //库存数是否充足
            if ($isMarginAdvanceProduct || $isFuturesAdvanceProduct) {
                if($v['quantity']>$quantity_list[$v['product_id']]){
                    $json['success'] = false;
                    $json['msg'] = sprintf($this->language->get('check_error_quantity'), $v['sku']);
                    break;
                }
                // 判断是不是期货头款
                if ($isFuturesAdvanceProduct) {
                    //期货二期，判断是否有足够的期货合约可用数量
                    if (!$this->model_futures_agreement->isEnoughContractQty($v['agreement_id'])) {
                        $json['success'] = false;
                        $json['msg'] =  sprintf($this->language->get('check_contract_error_quantity'), implode(', ', $this->model_futures_agreement->unEnoughContractAgreementNos($v['agreement_id'], $cartIds)));
                        break;
                    }
                    //期货二期，判断是否有足够的合约保证金
                    $contractRes = $this->model_futures_agreement->isEnoughContractMargin($v['agreement_id']);
                    if (!$contractRes['status']) {
                        $json['success'] = false;
                        $json['msg'] = sprintf($this->language->get('error_futures_low_deposit'), $contractRes['agreement_no']);
                        break;
                    }
                }


            }else{
                if ($v['quantity'] > $quantity) {
                    $json['success'] = false;
                    $json['msg'] = sprintf($this->language->get('check_error_quantity'), $v['sku']);
                    break;
                }
            }

            //购买数量不能为0
            if ($v['quantity'] <= 0) {
                $json['success'] = false;
                $json['msg'] = 'Quantity of Item Code can not be 0!';
                break;
            }

            $selectCartInfo[] = [
                'product_id' => $v['product_id'],
                'transaction_type' => $v['type_id'],
                'quantity' => $v['quantity'],
                'cart_id' => $v['cart_id'],
                'agreement_id' => $v['agreement_id'],
                'agreement_code' => $v['agreement_code'],
                'add_cart_type' => $v['add_cart_type'],
                'is_virtual_pay' => 0,
            ];
        }
        if (!isset($json['success'])) {
            $json['success'] = true;
            $json['buy_now_data'] = base64_encode(json_encode($selectCartInfo));
        }else{
            if($runId !=0){
                //购物车添加产品回退(仅用于下单页)
                $this->cart->deleteCart($cartIdArr);
            }else {
                session()->set('product_error', 'There are invalid items in your shopping cart.');
            }
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function checkPickUpCart()
    {
        //language
        $this->load->language('checkout/cwf_info');
        $this->load->model('checkout/cwf_info');
        $this->load->model('extension/module/price');
        $this->load->model('futures/agreement');

        $cartIdStr = get_value_or_default($this->request->post, 'cart_id_str', '');
        $deliveryType = get_value_or_default($this->request->post, 'delivery_type', 1);
        $cartIdArr = explode(',', $cartIdStr);

        $this->model = $this->model_checkout_cwf_info;
        $cart_info = $this->cart->getProducts(null, $deliveryType, $cartIdArr);
        $cart_info = array_combine(array_column($cart_info, 'cart_id'), $cart_info);
        $this->load->model('customerpartner/DelicacyManagement');
        $delicacy_model = $this->model_customerpartner_DelicacyManagement;
        $delicacy_info = $delicacy_model->checkIsDisplay_batch(array_column($cart_info, 'product_id'), $this->customer->getId());
        $quantity_list = $this->model->get_quantity(array_column($cart_info, 'product_id'));

        $cartIds = array_column($cart_info, 'cart_id');
        $selectCartInfo = [];
        foreach ($cart_info as $k => $v) {
            //是否为保证金头款商品
            $this->load->model('account/product_quotes/margin');
            $isMarginAdvanceProduct = $this->model_account_product_quotes_margin->isMarginAdvanceProduct($v['product_id']);

            //是否为期货头款商品 N-294
            $isFuturesAdvanceProduct = $this->model_futures_agreement->isFuturesAdvanceProduct($v['product_id']);
            /** @var ModelExtensionModulePrice $priceModel */
            $priceModel = $this->model_extension_module_price;
            $transactionQtyResults = $this->model_extension_module_price->getProductPriceInfo($v['product_id'], $this->customer->getId(), [], false, true, ['qty' => $v['quantity']]);
            //普通交易形式
            if ($v['type_id'] == ProductTransactionType::NORMAL) {
                if (!empty($qtyTypes[$v['cart_id']])) {
                    if ($qtyTypes[$v['cart_id']] == 'Promotion' && isset($transactionQtyResults['base_info']['time_limit_buy'])) {
                        $transactionQtyResults['base_info']['quantity'] = $transactionQtyResults['base_info']['time_limit_qty'];
                    }
                }
                $quantity = $transactionQtyResults['base_info']['quantity'];
            }elseif ($v['type_id'] == ProductTransactionType::REBATE){
                $quantity = $transactionQtyResults['base_info']['quantity'];
            }elseif ($v['type_id'] == ProductTransactionType::MARGIN){
                foreach ($transactionQtyResults['transaction_type'] as $transactionQty){
                    if($transactionQty['type'] == ProductTransactionType::MARGIN && $v['agreement_code'] == $transactionQty['agreement_code']){
                        $quantity = $transactionQty['left_qty'];
                        break;
                    }
                }
            }elseif ($v['type_id'] == ProductTransactionType::FUTURE){
                foreach ($transactionQtyResults['transaction_type'] as $transactionQty){
                    if($transactionQty['type'] == ProductTransactionType::FUTURE && $v['agreement_code'] == $transactionQty['agreement_code']){
                        $quantity = $transactionQty['left_qty'];
                        break;
                    }
                }
            }elseif ($v['type_id'] == ProductTransactionType::SPOT){
                foreach ($transactionQtyResults['transaction_type'] as $transactionQty){
                    if($transactionQty['type'] == ProductTransactionType::SPOT && $v['agreement_code'] == $transactionQty['agreement_code']){
                        if ($transactionQty['qty'] != $v['quantity']) {
                            $json['success'] = false;
                            $json['msg'] = $this->language->get('check_spot_agreement_error_quantity');
                            break 2;
                        }
                        $quantity = $transactionQty['left_qty'];
                        break;
                    }
                }
            }

            //上架且可独立售卖且Buyer可见
            if (!in_array($v['product_id'], $delicacy_info)) {
                $json['success'] = false;
                $json['msg'] = sprintf($this->language->get('check_error_available'), $v['sku']);
                break;
            }
            //库存数是否充足
            if ($isMarginAdvanceProduct || $isFuturesAdvanceProduct) {
                if($v['quantity']>$quantity_list[$v['product_id']]){
                    $json['success'] = false;
                    $json['msg'] = sprintf($this->language->get('check_error_quantity'), $v['sku']);
                    break;
                }
                // 判断是不是期货头款
                if ($isFuturesAdvanceProduct) {
                    //期货二期，判断是否有足够的期货合约可用数量
                    if (!$this->model_futures_agreement->isEnoughContractQty($v['agreement_id'])) {
                        $json['success'] = false;
                        $json['msg'] = sprintf($this->language->get('check_contract_error_quantity'), implode(', ', $this->model_futures_agreement->unEnoughContractAgreementNos($v['agreement_id'], $cartIds)));
                        break;
                    }
                    //期货二期，判断是否有足够的合约保证金
                    $contractRes = $this->model_futures_agreement->isEnoughContractMargin($v['agreement_id']);
                    if (!$contractRes['status']) {
                        $json['success'] = false;
                        $json['msg'] = sprintf($this->language->get('error_futures_low_deposit'), $contractRes['agreement_no']);
                        break;
                    }
                }
            }else{
                if ($v['quantity'] > $quantity) {
                    $json['success'] = false;
                    $json['msg'] = sprintf($this->language->get('check_error_quantity'), $v['sku']);
                    break;
                }
            }

            //购买数量不能为0
            if ($v['quantity'] <= 0) {
                $json['success'] = false;
                $json['msg'] = 'Quantity of Item Code can not be 0!';
                break;
            }

            $selectCartInfo[] = [
                'product_id' => $v['product_id'],
                'transaction_type' => $v['type_id'],
                'quantity' => $v['quantity'],
                'cart_id' => $v['cart_id'],
                'agreement_id' => $v['agreement_id'],
                'agreement_code' => $v['agreement_code'],
                'add_cart_type' => $v['add_cart_type'],
                'is_virtual_pay' => 0,
            ];
        }
        if (!isset($json['success'])) {
            $json['success'] = true;
            $json['buy_now_data'] = base64_encode(json_encode($selectCartInfo));
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function checkCwfCart()
    {
        //language
        $this->load->language('checkout/cwf_info');
        $this->load->model('checkout/cwf_info');
        $this->load->model('extension/module/price');
        $this->load->model('futures/agreement');

        $cartIdStr = $this->request->post('cart_id_str', '');
        $deliveryType = $this->request->post('delivery_type', 2);
        $cartInfo = json_decode($this->request->post('cart_info', '[]'), true);

        $cartIdArr = explode(',', $cartIdStr);
        $cartQuantity = array_column($cartInfo ?? [],'quantity','cart_id');
        $cartType = array_column($cartInfo ?? [],'type_id','cart_id');

        $this->model = $this->model_checkout_cwf_info;
        $cart_info = $this->cart->getProducts(null, $deliveryType, $cartIdArr);
        $cart_info = array_combine(array_column($cart_info, 'cart_id'), $cart_info);
        $this->load->model('customerpartner/DelicacyManagement');
        $delicacy_model = $this->model_customerpartner_DelicacyManagement;
        $delicacy_info = $delicacy_model->checkIsDisplay_batch(array_column($cart_info, 'product_id'), $this->customer->getId());
        $quantity_list = $this->model->get_quantity(array_column($cart_info, 'product_id'));
        $cwf_freight = $this->freight->getFreightAndPackageFeeByProducts(array_column($cart_info, 'product_id'));
        //校验云送仓的体积,不足2立方米，不允许发货
        $volumeAll = 0;
        $selectCartInfo = [];
        if(empty($cart_info) || count($cart_info) != count($cartIdArr)){
            //购物车信息不存在或者数量与查询的数量不符，则报错
            $json['success'] = false;
            $json['msg'] = $this->language->get('check_error_cart_item_data_change');
        } else {
            $cartIds = array_column($cart_info, 'cart_id');
            foreach ($cart_info as $k => $v) {
                //是否为保证金头款商品
                $this->load->model('account/product_quotes/margin');
                $isMarginAdvanceProduct = $this->model_account_product_quotes_margin->isMarginAdvanceProduct($v['product_id']);

                //是否为期货头款商品 N-294
                $isFuturesAdvanceProduct = $this->model_futures_agreement->isFuturesAdvanceProduct($v['product_id']);
                /** @var ModelExtensionModulePrice $priceModel */
                $priceModel = $this->model_extension_module_price;
                $transactionQtyResults = $this->model_extension_module_price->getProductPriceInfo($v['product_id'], $this->customer->getId(), [], false, true, ['qty' => $v['quantity']]);
                //普通交易形式
                if ($v['type_id'] == ProductTransactionType::NORMAL) {
                    if (!empty($qtyTypes[$v['cart_id']])) {
                        if ($qtyTypes[$v['cart_id']] == 'Promotion' && isset($transactionQtyResults['base_info']['time_limit_buy'])) {
                            $transactionQtyResults['base_info']['quantity'] = $transactionQtyResults['base_info']['time_limit_qty'];
                        }
                    }
                    $quantity = $transactionQtyResults['base_info']['quantity'];
                } elseif ($v['type_id'] == ProductTransactionType::REBATE) {
                    $quantity = $transactionQtyResults['base_info']['quantity'];
                } elseif ($v['type_id'] == ProductTransactionType::MARGIN) {
                    foreach ($transactionQtyResults['transaction_type'] as $transactionQty) {
                        if ($transactionQty['type'] == ProductTransactionType::MARGIN && $v['agreement_code'] == $transactionQty['agreement_code']) {
                            $quantity = $transactionQty['left_qty'];
                            break;
                        }
                    }
                } elseif ($v['type_id'] == ProductTransactionType::FUTURE) {
                    foreach ($transactionQtyResults['transaction_type'] as $transactionQty) {
                        if ($transactionQty['type'] == ProductTransactionType::FUTURE && $v['agreement_code'] == $transactionQty['agreement_code']) {
                            $quantity = $transactionQty['left_qty'];
                            break;
                        }
                    }
                } elseif ($v['type_id'] == ProductTransactionType::SPOT) {
                    foreach ($transactionQtyResults['transaction_type'] as $transactionQty) {
                        if ($transactionQty['type'] == ProductTransactionType::SPOT && $v['agreement_code'] == $transactionQty['agreement_code']) {
                            if ($transactionQty['qty'] != $v['quantity']) {
                                $json['success'] = false;
                                $json['msg'] = $this->language->get('check_spot_agreement_error_quantity');
                                break 2;
                            }
                            $quantity = $transactionQty['left_qty'];
                            break;
                        }
                    }
                }

                //上架且可独立售卖且Buyer可见
                if (!in_array($v['product_id'], $delicacy_info)) {
                    $json['success'] = false;
                    $json['msg'] = sprintf($this->language->get('check_error_available'), $v['sku']);
                    break;
                }
                //库存数是否充足
                if ($isMarginAdvanceProduct || $isFuturesAdvanceProduct) {
                    if ($v['quantity'] > $quantity_list[$v['product_id']]) {
                        $json['success'] = false;
                        $json['msg'] = sprintf($this->language->get('check_error_quantity'), $v['sku']);
                        break;
                    }
                    // 判断是不是期货头款
                    if ($isFuturesAdvanceProduct) {
                        //期货二期，判断是否有足够的期货合约可用数量
                        if (!$this->model_futures_agreement->isEnoughContractQty($v['agreement_id'])) {
                            $json['success'] = false;
                            $json['msg'] = sprintf($this->language->get('check_contract_error_quantity'), implode(', ', $this->model_futures_agreement->unEnoughContractAgreementNos($v['agreement_id'], $cartIds)));
                            break;
                        }
                        //期货二期，判断是否有足够的合约保证金
                        $contractRes = $this->model_futures_agreement->isEnoughContractMargin($v['agreement_id']);
                        if (!$contractRes['status']) {
                            $json['success'] = false;
                            $json['msg'] = sprintf($this->language->get('error_futures_low_deposit'), $contractRes['agreement_no']);
                            break;
                        }
                    }
                } else {
                    if ($v['quantity'] > $quantity) {
                        $json['success'] = false;
                        $json['msg'] = sprintf($this->language->get('check_error_quantity'), $v['sku']);
                        break;
                    }
                }
                //1363 校验库存是否发生变动,如果不存在库存就是0，强制刷新后重试
                if (isset($cartQuantity[$v['cart_id']]) && $v['quantity'] != $cartQuantity[$v['cart_id']]) {
                    $json['success'] = false;
                    $json['msg'] = $this->language->get('check_error_cart_item_data_change');
                    break;
                }
                //1363 校验购物车交易类型
                if (isset($cartType[$v['cart_id']])) {
                    $vType = $cartType[$v['cart_id']];
                    if ($vType == '0') {
                        if ($vType != $v['type_id']) {
                            $json['success'] = false;
                            $json['msg'] = $this->language->get('check_error_cart_item_data_change');
                            break;
                        }
                    } else {
                        $vType = explode('_', $vType);
                        if ($vType[1] != $v['type_id']) {
                            $json['success'] = false;
                            $json['msg'] = $this->language->get('check_error_cart_item_data_change');
                            break;
                        }
                    }
                }
                //保证购物车都是云送仓的
                if ($v['delivery_type'] != $deliveryType) {
                    $json['success'] = false;
                    $json['msg'] = $this->language->get('check_error_cart_item_data_change');
                    break;
                }
                //1363 购物车商品店铺下架、商品下架、精细化不可见不能购买
                $can_buy = $v['buyer_flag'] && $v['product_status'] && $v['store_status'] && !$v['fine_cannot_buy'] ? 1 : 0;
                if (!$can_buy) {
                    $json['success'] = false;
                    $json['msg'] = $this->language->get('check_error_cart_item_data_change');
                    break;
                }
                //尺寸运费信息是否存在
                if ($v['combo']) {
                    foreach ($cwf_freight[$v['product_id']] as $fre_k => $fre_v) {
                        if ($fre_v['volume_inch'] == 0 || $fre_v['freight'] == 0) {
                            $json['success'] = false;
                            $json['msg'] = sprintf($this->language->get('check_error_size_error'), $v['sku']);
                        }
                        //云送仓购物车产品体积合并
                        //102497 换成立方英尺
                        $volumeAll += $fre_v['volume_inch'] * $fre_v['qty'] * $v['quantity'];
                    }
                } else {
                    if (!isset($cwf_freight[$v['product_id']]) || $cwf_freight[$v['product_id']]['volume_inch'] == 0 || $cwf_freight[$v['product_id']]['freight'] == 0) {
                        $json['success'] = false;
                        $json['msg'] = sprintf($this->language->get('check_error_size_error'), $v['sku']);
                    }
                    //云送仓购物车产品体积合并
                    //102497 换成立方英尺
                    $volumeAll += $cwf_freight[$v['product_id']]['volume_inch'] * $v['quantity'];
                }
                //购买数量不能为0
                if ($v['quantity'] <= 0) {
                    $json['success'] = false;
                    $json['msg'] = 'Quantity of Item Code can not be 0!';
                    break;
                }
                $selectCartInfo[] = [
                    'product_id' => $v['product_id'],
                    'transaction_type' => $v['type_id'],
                    'quantity' => $v['quantity'],
                    'cart_id' => $v['cart_id'],
                    'agreement_id' => $v['agreement_id'],
                    'agreement_code' => $v['agreement_code'],
                    'add_cart_type' => $v['add_cart_type'],
                ];
            }
        }
        if (!isset($json['success'])) {
            //校验云送仓的体积,不足2立方米，不允许发货
            if(bccomp($volumeAll,CLOUD_LOGISTICS_VOLUME_LOWER) === -1){
                $json['success'] = false;
                $json['msg'] = sprintf($this->language->get('volume_require_msg'));
            }else{
                $json['success'] = true;
                $json['buy_now_data'] = base64_encode(json_encode($selectCartInfo));
            }
        }else{
            session()->set('show_cwf', 1);
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * 修改购物车交付类型
     * @param ModelAccountProductQuoteswkproductquotes $modelAccountProductQuoteswkproductquotes
     * @param ModelCheckoutCart $modelCheckoutCart
     * @param ModelExtensionModuleCartHome $modelExtensionModuleCartHome
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function change(ModelAccountProductQuoteswkproductquotes $modelAccountProductQuoteswkproductquotes, ModelCheckoutCart $modelCheckoutCart, ModelExtensionModuleCartHome  $modelExtensionModuleCartHome)
    {
        $this->load->language('checkout/cart');
        $json = array();

        if (!$this->request->input->has('key') || !$this->request->input->has('from_delivery_type') || !$this->request->input->has('to_delivery_type')) {
            return $this->response->json($json);
        }

        $cartId = $this->request->input->get('key');
        $fromDeliveryType = $this->request->input->get('from_delivery_type');
        $toDeliveryType = $this->request->input->get('to_delivery_type');

        $cart = $this->cart->getProductByCartId($cartId);
        if (empty($cart)) {
            return $this->response->json($json);
        }
        if ($cart['type_id'] == ProductTransactionType::FUTURE) {
            $apiId = ($this->session->has('api_id') ? $this->session->get('api_id') : 0);
            if (app(FuturesAgreementRepository::class)->cartExistFuturesAgreementOther($this->customer->getId(), $cart['product_id'], $cart['agreement_id'], $toDeliveryType, $apiId)) {
                $json['error'] = $this->language->get('error_futures_transaction_add_cart_exist');
                return $this->response->json($json);
            }
        }
        if ($cart['type_id'] == ProductTransactionType::SPOT && $modelAccountProductQuoteswkproductquotes->cartExistSpotAgreement($this->customer->getId(), $cart['product_id'], $cart['agreement_id'], $toDeliveryType)) {
            $json['error'] = $this->language->get('error_transaction_add_cart_exist');
            return $this->response->json($json);
        }

        $this->cart->change($cartId, $fromDeliveryType, $toDeliveryType);
        $modelCheckoutCart->updateCartProductStatus($this->customer->getId());
        $this->session->removeDeepByKey('vouchers', $this->request->post['key']);

        $freight_txt = $toDeliveryType == 2 ? 'Cloud Wholesale Fulfillment' : 'Drop Shipping';

        $cartUrl = url(['checkout/cart']);
        if ($toDeliveryType == 2) {
            $cartUrl .= "&show_cwf=1";
        }

        $json['success'] = sprintf($this->language->get('text_success_add_cart'),
            $this->url->link('product/product', 'product_id=' . $cart['product_id']),
            $cart['sku'],
            $cartUrl,
            $freight_txt);

        $carts_info = $modelExtensionModuleCartHome->getCartInfo($this->customer->getId());
        $totalMoney = $this->currency->format( $carts_info['total_price'], $this->session->get('currency'));
        $json['totalNum'] = $carts_info['quantity'];
        $json['totalMoney'] = $totalMoney;
        $json['total'] = sprintf($this->language->get('text_items'), $carts_info['quantity'], $totalMoney);
        $json['drop_ship_qty'] = $this->cart->productsNum(0);
        $json['home_pick_qty'] = $this->cart->productsNum(1);
        $json['cloud_logistics_qty'] = $this->cart->productsNum(2);

        return $this->response->json($json);
    }

    /**
     * 判断是否能切换
     * @param ModelAccountProductQuoteswkproductquotes $modelAccountProductQuoteswkproductquotes
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function checkCartChange(ModelAccountProductQuoteswkproductquotes $modelAccountProductQuoteswkproductquotes){
        $this->load->language('checkout/cart');
        $json = array();

        if (!$this->request->input->has('cart_id') || !$this->request->input->has('to_delivery_type')) {
            $json['success'] = false;
            $json['msg'] = 'Please select a product!';
            return $this->response->json($json);
        }

        $cartId = $this->request->input->get('cart_id');
        $toDeliveryType = $this->request->input->get('to_delivery_type');

        //检查目标购物车已有数量
        $cartNum = $this->cart->productsNum($toDeliveryType);
        if ($cartNum >= $this->config->get('cart_limit')) {
            $json['success'] = false;
            $json['msg'] = $this->language->get('error_limit_cart');
            return $this->response->json($json);
        }

        $productCartInfo = $this->cart->getCartProductInfo($cartId);
        //检验服务店铺，保证金店铺的头款不能移动购物车
        //校验是否是普通商品
        if ($productCartInfo['product_type'] != 0 && $productCartInfo['product_type'] != 3) {
            $json['success'] = false;
            $json['msg'] = ' Margin or Feature Advance Product can not add to Cloud Wholesale Fulfillment cart!!';
            return $this->response->json($json);
        }
        if((in_array($productCartInfo['customer_id'],array(340,491,631,838)))){
            $json['success'] = false;
            $json['msg'] = 'Service Product can not  add to Cloud Wholesale Fulfillment cart!';
            return $this->response->json($json);
        }
        if ($productCartInfo['type_id'] == ProductTransactionType::FUTURE) {
            $apiId = ($this->session->has('api_id') ? $this->session->get('api_id') : 0);
            if (app(FuturesAgreementRepository::class)->cartExistFuturesAgreementOther($this->customer->getId(), $productCartInfo['product_id'], $productCartInfo['agreement_id'], $toDeliveryType, $apiId)) {
                $json['success'] = false;
                $json['msg'] = $this->language->get('error_futures_transaction_add_cart_exist');
                return $this->response->json($json);
            }
        }

        if ($productCartInfo['type_id'] == ProductTransactionType::SPOT && $modelAccountProductQuoteswkproductquotes->cartExistSpotAgreement($this->customer->getId(), $productCartInfo['product_id'], $productCartInfo['agreement_id'], $toDeliveryType)) {
            $json['success'] = false;
            $json['msg'] = $this->language->get('error_transaction_add_cart_exist');
            return $this->response->json($json);
        }

        $canChange = $this->cart->checkCartChange($cartId, $toDeliveryType);
        if (!$canChange) {
            $json['success'] = false;
            if ($toDeliveryType == 2) {
                $json['msg'] = 'This product has other transaction type, it can not add to Cloud Wholesale Fulfillment cart!';
            }
            if ($toDeliveryType == 0) {
                $json['msg'] = 'This product has other transaction type, it can not add to Drop Shipping cart!';
            }
            return $this->response->json($json);
        }

        $json['success'] = true;
        $json['msg'] = 'Please select a product!';
        return $this->response->json($json);
    }

    public function checkInnerAutoBuy()
    {
        $this->load->model('checkout/cart');
        $inner_auto_buy = $this->customer->innerAutoBuyAttr1();//内部自动购买产销异体账号
        $cartIdStr = get_value_or_default($this->request->post, 'cart_id_str', '');
        $deliveryType = get_value_or_default($this->request->post, 'delivery_type', 0);
        $cartIdArr = explode(',', $cartIdStr);
        $from_page = $this->request->input->get('from_page');//来自商品详情页的参数
        $product_id = $this->request->input->getInt('product_id', 0);//来自商品详情页的参数
        $quantity = max($this->request->input->getInt('quantity', 1), 1);//来自商品详情页的参数
        $data['flag'] = 0;
        if ($from_page == 'product') {
            if ($inner_auto_buy && in_array($deliveryType, [0,1]) && $product_id){
                $productQtyArr = [$product_id => $quantity];
                $tobePaid = $this->model_checkout_cart->checkPurchaseAndSales($deliveryType,[], $productQtyArr);
                if (!empty($tobePaid)){
                    $data['flag'] = 1;
                    $data['tobePaid'] = $tobePaid;
                }
            }
        } else {
            if ($inner_auto_buy && in_array($deliveryType, [0,1]) && $cartIdArr){
                $tobePaid = $this->model_checkout_cart->checkPurchaseAndSales($deliveryType,$cartIdArr);
                if (!empty($tobePaid)){
                    $data['flag'] = 1;
                }
            }
        }
        $this->response->success($data);
    }

    //自动购买-采销异体 防止囤货
    public function checkPurchaseAndSales()
    {
        $this->load->model('checkout/cart');
        $cartIdStr = get_value_or_default($this->request->get, 'cart_id_str', '');
        $deliveryType = get_value_or_default($this->request->get, 'delivery_type', 0);
        $cartIdArr = explode(',', $cartIdStr);
        $data['toBePaid'] = $this->model_checkout_cart->checkPurchaseAndSales($deliveryType,$cartIdArr);
        $data['updateInnerAutoBuyCartUrl'] = $this->url->link('checkout/cart/updateInnerAutoBuyCart');
        $data['preOrderUrl'] = $this->url->link('checkout/pre_order');
        $data['delivery_type'] = $deliveryType;

        $this->response->setOutput($this->load->view('checkout/inner_auto_buy_modal', $data));
    }

    //自动购买-采销异体 满足虚拟支付条件的 下单前修正购物车
    public function updateInnerAutoBuyCart()
    {
        $this->load->model('checkout/cart');
        $inner_auto_buy = $this->customer->innerAutoBuyAttr1();//内部自动购买产销异体账号
        $cartIdStr = get_value_or_default($this->request->post, 'cart_id_str', '');
        $deliveryType = get_value_or_default($this->request->post, 'delivery_type', 0);
        $cartIdArr = explode(',', $cartIdStr);
        if ($inner_auto_buy && in_array($deliveryType, [0,1]) && $cartIdArr){
            $toBuyCartId = $this->model_checkout_cart->updateInnerAutoBuyCart($deliveryType,$cartIdArr);
            $data = [
                'delivery_type' => $deliveryType,
                'cart_id_str'   => implode(',', $toBuyCartId)
            ];
            $this->response->success($data,'successfully');
        }
    }

    //修改数量
    public function updateQty()
    {
        $this->load->model('checkout/cart');
        $this->load->model('account/cart/cart');
        $cartId = $this->request->input->get('cart_id', 0);
        $qty = $this->request->input->get('qty', 0);

        if (!$this->model_checkout_cart->cartExist($cartId)) {
            return $this->jsonFailed('not found!');
        }

        $data = [];
        if ($cartId && $qty){
            $flag = $this->model_checkout_cart->updateQty($cartId, $qty);
            if ($flag){
                $data = $this->model_account_cart_cart->cartShowSingle($cartId);
            }
        }

        return $this->jsonSuccess($data,'successfully');
    }

    //修改交易方式
    public function updateTransactionType()
    {
        $this->load->model('checkout/cart');
        $this->load->model('account/cart/cart');
        $cartId = $this->request->input->get('cart_id', 0);
        $transactionType = $this->request->input->get('transaction_type', 0);

        if (!$this->model_checkout_cart->cartExist($cartId)) {
            return $this->jsonFailed('not found!');
        }
        $tmp = explode('_', $transactionType);
        $transactionTypeInt = $tmp[1] ?? $tmp[0];

        $data = [];
        if ($cartId && in_array($transactionTypeInt, [
                ProductTransactionType::NORMAL,
                ProductTransactionType::REBATE,
                ProductTransactionType::MARGIN,
                ProductTransactionType::FUTURE,
                ProductTransactionType::SPOT
            ])) {
            $this->model_checkout_cart->updateCart($cartId, null, $transactionType);
            $data = $this->model_account_cart_cart->cartShowSingle($cartId);
        }

        return $this->jsonSuccess($data,'successfully');
    }

    //修改商品
    public function changeCartProduct()
    {
        $this->load->model('checkout/cart');
        $this->load->model('extension/module/cart_home');
        $cartId = get_value_or_default($this->request->post, 'cart_id', 0);
        $toProductId = get_value_or_default($this->request->post,'to_product_id', 0);
        $ret = ['flag'=>false, 'data'=>0];
        if ($cartId && $toProductId){
            $ret = $this->model_checkout_cart->changeCartProduct($cartId, $toProductId);
        }
        if ($ret['flag']){
            $carts_info =  $this->model_extension_module_cart_home->getCartInfo($this->customer->getId());
            $totalMoney = $this->currency->format($carts_info['total_price'], session('currency'));
            $ret['total'] = [
                'totalNum'  => $carts_info['quantity'],
                'totalMoney'=> $totalMoney
            ];
            $this->response->success($ret,'successfully');
        }else{
            $this->response->failed(['msg'=>'failed.']);
        }
    }

    //批量移除购物车
    public function batchRemove()
    {
        $this->load->model('extension/module/cart_home');

        $cartIdStr = get_value_or_default($this->request->post, 'cart_id_str', '');
        $cartIdArr = explode(',', $cartIdStr);
        $ret = [];
        $flag = 0;
        if ($cartIdArr){
            $flag = $this->cart->batchRemove($cartIdArr, $this->customer->getId());
            $carts_info =  $this->model_extension_module_cart_home->getCartInfo($this->customer->getId());
            $totalMoney = $this->currency->format($carts_info['total_price'], session('currency'));
            $ret['total'] = [
                'totalNum'  => $carts_info['quantity'],
                'totalMoney'=> $totalMoney
            ];
        }
        if ($flag){
            $this->response->success($ret,'successfully');
        }else{
            $this->response->failed('请勿重复操作');
        }

    }

    //实时计算购物车金额
    public function totalByCartId()
    {
        $this->load->model('account/cart/cart');

        $cartIdStr = get_value_or_default($this->request->post, 'cart_id_str', '');
        if ($cartIdStr){
            $cartIdArr = explode(',', $cartIdStr);
            $data = $this->model_account_cart_cart->orderTotalShow($cartIdArr);
        }else{
            $data = $this->model_account_cart_cart->emptyTotalShow();
        }

        $this->response->success($data,'successfully');
    }
    //根据下单页的数据加入购物车,仅用于从下单页加入其余情况勿用
    public function addCartByRunId(){
        $this->load->language('checkout/cart');
        $this->load->model('checkout/cart');
        $this->load->model('futures/agreement');
        $cart_model = $this->model_checkout_cart;
        $runId = get_value_or_default($this->request->post, 'run_id', 0);
        $json = array();
        $json['status'] = true;
        $customer_id = $this->customer->getId();
        $this->load->model('account/sales_order/match_inventory_window');
        $matchModel = $this->model_account_sales_order_match_inventory_window;
        //获取下单页数据
        $purchaseRecords = $matchModel->getPurchaseRecord($runId, $customer_id);
        $addCartProductArray = [];
        if( $runId !=0 && !empty($purchaseRecords)){
            foreach ($purchaseRecords as $purchaseRecord){
                //新增了逻辑相同产品的不同交易方式不允许同时添加购物车
                $count = $cart_model->verifyProductAdd($purchaseRecord['product_id'], $purchaseRecord['type_id'], $purchaseRecord['agreement_id'],0);
                if ($count) {
                    $json['status'] = false;
                    $json['error'] = $this->language->get('error_transaction_add_cart');
                    break;
                }
                //合并需要加入购物车的产品
                if(!isset($addCartProductArray[$purchaseRecord['product_id']])) {
                    $addCartProductArray[$purchaseRecord['product_id']] = array(
                        'api_id' => (int)session('api_id', 0),
                        'customer_id' => (int)$this->customer->getId(),
                        'session_id' => $this->session->getId(),
                        'product_id' => $purchaseRecord['product_id'],
                        'recurring_id' => 0,
                        'option' => json_encode([]),
                        'type_id' => $purchaseRecord['type_id'],
                        'quantity'     =>  $purchaseRecord['quantity'],
                        'date_added'   =>  date('Y-m-d H:i:s',time()),
                        'agreement_id' => $purchaseRecord['agreement_id'],
                        'delivery_type' => 0,
                    );
                }else{
                    //该product_id已经存在，校验支付方式
                    $quantity = $addCartProductArray[$purchaseRecord['product_id']]['quantity'];
                    $type_id = $addCartProductArray[$purchaseRecord['product_id']]['type_id'];
                    $agreement_id = $addCartProductArray[$purchaseRecord['product_id']]['agreement_id'];
                    $this_type_id = $purchaseRecord['type_id'];
                    $this_agreement_id = $purchaseRecord['agreement_id'];
                    $this_quantity = $purchaseRecord['quantity'];
                    if($type_id != $this_type_id || $agreement_id != $this_agreement_id){
                        $json['status'] = false;
                        $json['error'] = $this->language->get('error_transaction_add_cart');
                        break;
                    }else{
                        $addCartProductArray[$purchaseRecord['product_id']]['quantity'] =  $quantity+$this_quantity;
                    }
                }
            }
        }else{
            $json['status'] = false;
            $json['error'] = 'no sales order to buy';
            $json['redirect'] = $this->url->link('account/account', '', true);
        }
        if($json['status'] !=false ) {
            //批量加入购物车
            $result = $cart_model->addByArr($addCartProductArray);
            if (!$result) {
                //添加购物车异常
                $json['status'] = false;
                $json['error'] = $this->language->get('add_cart_error');
            } else {
                //拼接数据cart_id
                $cart_id_str = join(",", $result);
                $json['status'] = true;
                $json['cart_id_str'] = $cart_id_str;
            }
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * BUY NOW 直接购买
     * @return \Framework\Http\Response
     * @throws Exception
     * 参考 storage\modification\catalog\controller\checkout\cart.php add()方法
     */
    public function buyNow()
    {
        $this->load->language('checkout/cart');
        $this->load->model('customerpartner/DelicacyManagement');
        $this->load->model('catalog/product');
        $this->load->model('checkout/cart');
        $this->load->model('checkout/pre_order');
        $this->load->model('futures/agreement');
        $this->load->model('extension/module/cart_home');
        $this->load->model('customerpartner/master');

        $product_id = max($this->request->input->getInt('product_id'), 0);
        $quantity = max($this->request->input->getInt('quantity'), 1);
        $transaction_type = $this->request->input->get('transaction_type');
        $agreement_code = $this->request->input->get('agreement_code');
        $freight_radio = $this->request->input->get('freight_radio');
        $add_cart_type = $this->request->input->getInt('add_cart_type', CartAddCartType::DEFAULT_OR_OPTIMAL);
        $option = $this->request->input->get('option') ?? [];
        $recurring_id = max($this->request->input->getInt('recurring_id'), 0);
        $is_virtual_pay = $this->request->input->get('is_virtual_pay', null);

        if(!is_null($freight_radio) && $freight_radio=='cwf'){
            $delivery_type = 2;
        }else{
            $delivery_type=null;
        }

        $cart_model = $this->model_checkout_cart;
        $json = array();

        $sellerInfo = $this->model_customerpartner_master->getInfoByProductId($product_id);
        if(!$sellerInfo || $sellerInfo['status'] == 0){
            $json['error']['seller'] = $this->language->get('error_seller');
            goto end;
        }

        $unsupportStockMap = app(CustomerRepository::class)->getUnsupportStockData([$product_id]);
        if(in_array($product_id,$unsupportStockMap)){
            $json['error']['seller'] = $this->language->get('error_unsupport_stock_limit');
            goto end;
        }

        //检验是否精细化不能购买
        $result = $this->model_customerpartner_DelicacyManagement->getProductPrice($product_id, $this->customer->getId());
        if (empty($result)) {
            $json['error']['transaction_type'] = $this->language->get('error_add_cart');
        }
        $product_info = $this->model_catalog_product->getProduct($product_id);
        if ($product_info) {
            $agreement_id = null;
            $type = 0;
            //195_2 agreement_id  k price_value price_all type qty
            if (!is_null($transaction_type)) {
                if ($transaction_type != 0) {
                    $info = explode('_', $transaction_type);
                    $agreement_id = $info[0];
                    //验证协议是否失效
                    $type = $info[1];
                    $transaction_info = $this->cart->getTransactionTypeInfo($type,$agreement_id,$product_id);
                    if($this->config->get('common_performer_type_margin_spot') == $type){
                        if(!$transaction_info){
                            // 获取agreement id
                            $agreement_code = $cart_model->getTransactionTypeInfo($type,$agreement_id,$product_id);
                            $json['error']['transaction_type']=  sprintf($this->language->get('error_expire_time_add_cart'),$agreement_code);
                        }
                    }elseif ($this->config->get('common_performer_type_margin_futures') == $type
                        && 6 != $transaction_info['delivery_status']){
                        $json['error']['transaction_type'] = sprintf($this->language->get('error_expire_time_add_cart'),
                            $transaction_info['agreement_no']);
                    }
                    //现货保证金协议 过期
                    if ($type == ProductTransactionType::MARGIN && $transaction_info) {
                        if ($transaction_info['expire_time'] < date('Y-m-d H:i:s')) {
                            $json['error']['transaction_type'] = sprintf($this->language->get('error_expire_time_add_cart'), $transaction_info['agreement_code']);
                            goto end;
                        }
                    }
                } else {
                    //验证是否是保证金头款
                    $map = [
                        'process_status' => 1,
                        'advance_product_id' => $product_id,
                    ];
                    $agreement_id = $this->orm->table('tb_sys_margin_process')->where($map)->value('margin_id');
                    if ($agreement_id) {
                        $type = ProductTransactionType::MARGIN;
                    }else{//验证是不是期货头款
                        $agreement_id = $this->model_futures_agreement->getFuturesIdByAdvanceProductId($product_id);
                        $type = $agreement_id ? ProductTransactionType::FUTURE : $type;
                    }
                }
            } else {
                $map = [
                    'process_status' => 1,
                    'advance_product_id' => $product_id,
                ];
                $agreement_id = $this->orm->table('tb_sys_margin_process')->where($map)->value('margin_id');
                if ($agreement_id) {
                    $type = ProductTransactionType::MARGIN;
                }
            }


            $product_options = $this->model_catalog_product->getProductOptions($product_id);
            foreach ($product_options as $product_option) {
                if ($product_option['required'] && empty($option[$product_option['product_option_id']])) {
                    $json['error']['option'][$product_option['product_option_id']] = sprintf($this->language->get('error_required'), $product_option['name']);
                }
            }

            if (!$json) {
                $tmp=[
                    'product_id' => $product_id,
                    'transaction_type' => $type,
                    'quantity' => $quantity,
                    'cart_id' => '',
                    'agreement_id' => $agreement_id,
                    'agreement_code' => $agreement_code,
                    'add_cart_type' => $add_cart_type,
                ];
                if ((!is_null($is_virtual_pay)) && in_array($is_virtual_pay, [0, 1])) {
                    $tmp['is_virtual_pay'] = $is_virtual_pay;
                }
                $parameter = [
                    $tmp
                ];
                $json['buy_now_data'] = base64_encode(json_encode($parameter));

                $json['success'] = "Success";
                // Unset all shipping and payment methods
                $this->session->remove('shipping_method');
                $this->session->remove('shipping_methods');
                $this->session->remove('payment_method');
                $this->session->remove('payment_methods');
            } else {
                $json['redirect'] = $this->url->to(['product/product', 'product_id' => $product_id]);
            }
        }

        end:
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
}
