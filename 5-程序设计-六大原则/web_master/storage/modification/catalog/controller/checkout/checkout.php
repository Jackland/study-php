<?php

class ControllerCheckoutCheckout extends Controller
{
    public function index()
    {
        // Validate cart has products and has stock.
        //清空delivery_type
        $this->session->remove('delivery_type');
        $data['header'] = $this->load->controller('common/header');
        if (isset($this->request->get['delivery_type'])) {
            session()->set('delivery_type', $this->request->get['delivery_type']);
        }else{
            $this->response->redirect($this->url->link('checkout/cart'));
        }
        if ((!$this->cart->hasProducts(session('delivery_type')) && empty($this->session->data['vouchers'])) || (!$this->cart->hasStock() && !$this->config->get('config_stock_checkout'))) {
            $this->response->redirect($this->url->link('checkout/cart'));
        }
        // add by lilei 检查是否使用余额（信用额度余额）支付
        $this->session->remove('useBalanceToZero');
        if ($this->config->get('total_balance_status')) {
            if( session('delivery_type') != 2) {
                if (isset($this->request->post['balanceValue'])) {
                    $balance = $this->request->post['balanceValue'];
                    // 获取当前用户信用额度，判断传递的使用余额是否合法
                    $lineOfCredit = $this->customer->getLineOfCredit();
                    if (bccomp($balance, $this->cart->getQuoteTotal(), 2) > 0) {
                        session()->set('error', 'The product price has changed, please checkout again.');
                        $this->response->redirect($this->url->link('checkout/cart'));
                    }
                    if (bccomp($balance, $lineOfCredit, 2) > 0) {
                        // 支付的余额大于实际信用额度，页面重定向
                        $this->response->redirect($this->url->link('checkout/cart'));
                        // 余额大于订单总金额
                    } else {
                        // 保存Session信息
                        session()->set('useBalance', $balance);
                        $this->load->model('buyer/buyer_common');
                        $total = (double)$this->model_buyer_buyer_common->getCartTotal();
                        if ($total == 0) {
                            //使用组合支付且价格扣减为0
                            session()->set('useBalance', 0);
                            session()->set('useBalanceToZero', true);
                        }
                    }
                }
            }else{
                if (isset($this->session->data['balanceValue'])) {
                    $balance = session('balanceValue');
                    $this->session->remove('balanceValue');
                    // 获取当前用户信用额度，判断传递的使用余额是否合法
                    $lineOfCredit = $this->customer->getLineOfCredit();
                    if (bccomp($balance, $this->cart->getQuoteTotal(), 2) > 0) {
                        session()->set('error', 'The product price has changed, please checkout again.');
                        $this->response->redirect($this->url->link('checkout/cart'));
                    }
                    if (bccomp($balance, $lineOfCredit, 2) > 0) {
                        // 支付的余额大于实际信用额度，页面重定向
                        $this->response->redirect($this->url->link('checkout/cart'));
                        // 余额大于订单总金额
                    } else {
                        // 保存Session信息
                        session()->set('useBalance', $balance);
                        $this->load->model('buyer/buyer_common');
                        $total = (double)$this->model_buyer_buyer_common->getCartTotal();
                        if ($total == 0) {
                            //使用组合支付且价格扣减为0
                            session()->set('useBalance', 0);
                            session()->set('useBalanceToZero', true);
                        }
                    }
                }
            }
        } else {
            session()->set('useBalance', 0);
        }

        $this->load->model('account/customerpartner');

        if ($this->config->get('module_marketplace_status') && (((int)$this->config->get('marketplace_min_cart_value') && (int)$this->config->get('marketplace_min_cart_value') > $this->cart->getTotal()) || ($this->model_account_customerpartner->getProductRestriction()))) {
            $this->response->redirect($this->url->link('checkout/cart'));
        }

        // Validate minimum quantity requirements.
        $products = $this->cart->getProducts(null, session('delivery_type'));


        if ($this->config->get('module_marketplace_status')) {
            $productName = '';
        }

        foreach ($products as $product) {
            $product_total = 0;


            if ($this->config->get('module_marketplace_status')) {

                $this->load->model('account/customerpartner');

                $checkSellerOwnProduct = $this->model_account_customerpartner->checkSellerOwnProduct($product['product_id']);

                if ($checkSellerOwnProduct && !$this->config->get('marketplace_sellerbuyproduct')) {
                    $allowedProductBuy = false;
                } else {
                    $allowedProductBuy = true;
                }

                if (!$allowedProductBuy) {
                    $productName .= $product['name'] . ', ';
                    session()->set('sellerProducts', $productName);
                    $this->response->redirect($this->url->link('checkout/cart'));
                }
            }

            foreach ($products as $product_2) {
                if ($product_2['product_id'] == $product['product_id']) {
                    $product_total += $product_2['quantity'];
                }
            }

            if ($product['minimum'] > $product_total) {
                $this->response->redirect($this->url->link('checkout/cart'));
            }
        }

        $this->load->language('checkout/checkout');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->document->addScript('catalog/view/javascript/jquery/datetimepicker/moment/moment.min.js');
        $this->document->addScript('catalog/view/javascript/jquery/datetimepicker/moment/moment-with-locales.min.js');
        $this->document->addScript('catalog/view/javascript/jquery/datetimepicker/bootstrap-datetimepicker.min.js');
        $this->document->addStyle('catalog/view/javascript/jquery/datetimepicker/bootstrap-datetimepicker.min.css');

        // Required by klarna
        if ($this->config->get('payment_klarna_account') || $this->config->get('payment_klarna_invoice')) {
            $this->document->addScript('http://cdn.klarna.com/public/kitt/toc/v1.0/js/klarna.terms.min.js');
        }

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/home')
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_cart'),
            'href' => $this->url->link('checkout/cart')
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('checkout/checkout', '', true)
        );

        $data['text_checkout_option'] = sprintf($this->language->get('text_checkout_option'), 1);
        $data['text_checkout_account'] = sprintf($this->language->get('text_checkout_account'), 2);
        $data['text_checkout_payment_address'] = sprintf($this->language->get('text_checkout_payment_address'), 2);
        $data['text_checkout_shipping_address'] = sprintf($this->language->get('text_checkout_shipping_address'), 3);
        $data['text_checkout_shipping_method'] = sprintf($this->language->get('text_checkout_shipping_method'), 4);

        if ($this->cart->hasShipping()) {
//            $data['text_checkout_payment_method'] = sprintf($this->language->get('text_checkout_payment_method'), 5);
//            $data['text_checkout_confirm'] = sprintf($this->language->get('text_checkout_confirm'), 6);
            // comment by lilei , 修改checkout 下单步骤流程 20181105
            $data['text_checkout_payment_method'] = sprintf($this->language->get('text_checkout_payment_method'), 2);
            $data['text_checkout_confirm'] = sprintf($this->language->get('text_checkout_confirm'), 3);
        } else {
            $data['text_checkout_payment_method'] = sprintf($this->language->get('text_checkout_payment_method'), 3);
            $data['text_checkout_confirm'] = sprintf($this->language->get('text_checkout_confirm'), 4);
        }

        if (isset($this->session->data['error'])) {
            $data['error_warning'] = session('error');
            $this->session->remove('error');
        } else {
            $data['error_warning'] = '';
        }

        $data['logged'] = $this->customer->isLogged();

        if (isset($this->session->data['account'])) {
            $data['account'] = session('account');
        } else {
            $data['account'] = '';
        }

        $data['shipping_required'] = $this->cart->hasShipping();

        $data['column_left'] = $this->load->controller('common/column_left');
        $data['column_right'] = $this->load->controller('common/column_right');
        $data['content_top'] = $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');
        $data['footer'] = $this->load->controller('common/footer');
        // 定义payment_address 和 shipping_address
        $this->load->model('account/address');
        session()->set('payment_address', $this->model_account_address->getAddress($this->customer->getAddressId()));
        session()->set('shipping_address', $this->model_account_address->getAddress($this->customer->getAddressId()));

        $this->response->setOutput($this->load->view('checkout/checkout', $data));
    }

    public function country()
    {
        $json = array();

        $this->load->model('localisation/country');

        $country_info = $this->model_localisation_country->getCountry($this->request->get['country_id']);

        if ($country_info) {
            $this->load->model('localisation/zone');

            $json = array(
                'country_id' => $country_info['country_id'],
                'name' => $country_info['name'],
                'iso_code_2' => $country_info['iso_code_2'],
                'iso_code_3' => $country_info['iso_code_3'],
                'address_format' => $country_info['address_format'],
                'postcode_required' => $country_info['postcode_required'],
                'zone' => $this->model_localisation_zone->getZonesByCountryId($this->request->get['country_id']),
                'status' => $country_info['status']
            );
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function customfield()
    {
        $json = array();

        $this->load->model('account/custom_field');

        // Customer Group
        if (isset($this->request->get['customer_group_id']) && is_array($this->config->get('config_customer_group_display')) && in_array($this->request->get['customer_group_id'], $this->config->get('config_customer_group_display'))) {
            $customer_group_id = $this->request->get['customer_group_id'];
        } else {
            $customer_group_id = $this->config->get('config_customer_group_id');
        }

        $custom_fields = $this->model_account_custom_field->getCustomFields($customer_group_id);

        foreach ($custom_fields as $custom_field) {
            $json[] = array(
                'custom_field_id' => $custom_field['custom_field_id'],
                'required' => $custom_field['required']
            );
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
}