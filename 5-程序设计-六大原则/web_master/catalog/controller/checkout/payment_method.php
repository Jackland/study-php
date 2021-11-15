<?php

use App\Enums\Pay\PayCode;

/**
 * @deprecated
 * @property ModelAccountCustomer $model_account_customer
 * @property ModelBuyerBuyerCommon $model_buyer_buyer_common
 * @property ModelCatalogInformation $model_catalog_information
 * @property ModelCheckoutPay $model_checkout_pay
 * @property ModelSettingExtension $model_setting_extension
 * */
class ControllerCheckoutPaymentMethod extends Controller {
	public function index() {
        $this->load->language('checkout/checkout');
        // add by lilei || 1 过滤判断
//        if (isset($this->session->data['shipping_address'])) {
//            session()->set('payment_address', session('shipping_address'));
//        }
		if (isset($this->session->data['payment_address'])) {
			// Totals
			$totals = array();
			$taxes = $this->cart->getTaxes();
			$total = 0;

			// Because __call can not keep var references so we put them into an array.
			$total_data = array(
				'totals' => &$totals,
				'taxes'  => &$taxes,
				'total'  => &$total
			);

			$this->load->model('setting/extension');

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

			// Payment Methods
			$method_data = array();

			$this->load->model('setting/extension');

			$results = $this->model_setting_extension->getExtensions('payment');

			$recurring = $this->cart->hasRecurringProducts();

            $this->load->model('account/customer');
            $isUSBuyer = $this->model_account_customer->isUSBuyer();

			foreach ($results as $result) {
                if ($this->config->get('payment_' . $result['code'] . '_status')) {
                    //美国用户不展示umf_pay和wechat_pay
                    if ($result['code'] == 'umf_pay' || $result['code'] == 'wechat_pay') {
                        if ($isUSBuyer) {
                            continue;
                        }
                    }
                    $this->load->model('extension/payment/' . $result['code']);

                    $method = $this->{'model_extension_payment_' . $result['code']}->getMethod(session('payment_address'), $total);

					if ($method) {
						if ($recurring) {
							if (property_exists($this->{'model_extension_payment_' . $result['code']}, 'recurringPayments') && $this->{'model_extension_payment_' . $result['code']}->recurringPayments()) {
								$method_data[$result['code']] = $method;
							}
						} else {
							$method_data[$result['code']] = $method;
						}
					}
				}
			}

			$sort_order = array();

			foreach ($method_data as $key => $value) {
				$sort_order[$key] = $value['sort_order'];
			}

			array_multisort($sort_order, SORT_ASC, $method_data);

			session()->set('payment_methods', $method_data);
		}

        if (empty($this->session->data['payment_methods'])) {
            $data['error_warning'] = sprintf($this->language->get('error_no_payment'), $this->url->link('information/contact'));
        } else {
            $data['error_warning'] = '';
        }

        if (isset($this->session->data['payment_methods'])) {
            $data['payment_methods'] = session('payment_methods');
        } else {
            $data['payment_methods'] = array();
        }

        if (isset($this->session->data['payment_method']['code'])) {
            $data['code'] = $this->session->data['payment_method']['code'];
        } else {
            $data['code'] = '';
        }

        if (isset($data['payment_methods']['line_of_credit']) && $this->customer->getAdditionalFlag() == 1) {
            $data['payment_methods']['line_of_credit']['title'] = 'Line Of Credit (+1%)';
        }

        if (isset($this->session->data['comment'])) {
            $data['comment'] = session('comment');
        } else {
            $data['comment'] = '';
        }

        $data['scripts'] = $this->document->getScripts();

        if ($this->config->get('config_checkout_id')) {
            $this->load->model('catalog/information');

            $information_info = $this->model_catalog_information->getInformation($this->config->get('config_checkout_id'));

            if ($information_info) {
                $data['text_agree'] = sprintf($this->language->get('text_agree'), $this->url->link('information/information/agree', 'information_id=' . $this->config->get('config_checkout_id'), true), $information_info['title'], $information_info['title']);
            } else {
                $data['text_agree'] = '';
            }
        } else {
            $data['text_agree'] = '';
        }

        if (isset($this->session->data['agree'])) {
            $data['agree'] = session('agree');
        } else {
            $data['agree'] = '';
        }
        //customer

        $data['balance'] = $this->currency->format($this->customer->getLineOfCredit(), session('currency'));

        if (isset($data['code']) && $data['code'] == '') {
            $tmp = current($data['payment_methods']);
            $data['code'] = $tmp['code'];
        }

        //使用组合支付且价格扣减为0
        if (isset($this->session->data['useBalanceToZero'])) {
            $data['useBalanceToZero'] = 1;
            $data['code'] = PayCode::PAY_LINE_OF_CREDIT;
        }
        if (isset($this->session->data['wechatInfo'])) {
            $data['wechatInfo'] = session('wechatInfo');
        }
        $this->response->setOutput($this->load->view('checkout/payment_method', $data));
    }

	public function save1() {
        $this->load->language('checkout/checkout');
        $this->load->model('buyer/buyer_common');

        $json = array();

        // Validate if payment address has been set.
        if (!isset($this->session->data['payment_address'])) {
            $json['redirect'] = $this->url->link('checkout/checkout', '', true);
        }

        // Validate cart has products and has stock.
        if ((!$this->cart->hasProducts() && empty($this->session->data['vouchers'])) || (!$this->cart->hasStock() && !$this->config->get('config_stock_checkout'))) {
            $json['redirect'] = $this->url->link('checkout/cart');
        }

        // Validate minimum quantity requirements.
        $products = $this->cart->getProducts(null, session('delivery_type'));

        foreach ($products as $product) {
            $product_total = 0;

            foreach ($products as $product_2) {
                if ($product_2['product_id'] == $product['product_id']) {
                    $product_total += $product_2['quantity'];
                }
            }

            if ($product['minimum'] > $product_total) {
                $json['redirect'] = $this->url->link('checkout/cart');

                break;
            }
        }

        if (!isset($this->request->post['payment_method'])) {
            $json['error']['warning'] = $this->language->get('error_payment');
        } elseif (!isset($this->session->data['payment_methods'][$this->request->post['payment_method']])) {
            $json['error']['warning'] = $this->language->get('error_payment');
        }
        if ($this->request->post('payment_method') == PayCode::PAY_LINE_OF_CREDIT) {
            // 支付方式如果是信用额度，需要判断信用额度余额
            //$total = (double)$this->cart->getTotal();
            if (isset($this->session->data['useBalance'])) {
                $useBalance = session('useBalance');
            } else {
                $useBalance = 0;
            }
            $total = (double)$this->model_buyer_buyer_common->getCartTotal();  // By Lester.You 2019-4-9 14:14:27
            $lineOfCredit = (double)$this->customer->getLineOfCredit() - $useBalance;
            if (bccomp($total, $lineOfCredit, 2) > 0) {
                $json['error']['warning'] = "Insufficient credit limit, please choose another payment method";
            }
        }
        if ($this->config->get('config_checkout_id')) {
            $this->load->model('catalog/information');

            $information_info = $this->model_catalog_information->getInformation($this->config->get('config_checkout_id'));

            if ($information_info && !isset($this->request->post['agree'])) {
                $json['error']['warning'] = sprintf($this->language->get('error_agree'), $information_info['title']);
            }
        }

        if (!$json) {
            session()->set('payment_method', $this->session->data['payment_methods'][$this->request->post['payment_method']]);

            session()->set('comment', strip_tags($this->request->post['comment']));
            //支付方式
            $json['payment_method'] = $this->request->post['payment_method'];
        }
        // 判断余额使用
        if (isset($this->session->data['useBalance'])) {
            $balance = session('useBalance');
            // 获取当前用户信用额度，判断传递的使用余额是否合法
            $lineOfCredit = $this->customer->getLineOfCredit();
            if (bccomp($balance, $this->cart->getQuoteTotal(), 2) > 0) {
                session()->set('error', 'The product price has changed, please checkout again.');
                $json['error']['url'] = $this->url->link('checkout/cart');
            }
            if (bccomp($balance, $lineOfCredit, 2) > 0) {
                // 支付的余额大于实际信用额度，页面重定向
                $this->response->redirect($this->url->link('checkout/cart'));
                // 余额大于订单总金额
            } else {
                // 保存Session信息
                session()->set('useBalance', $balance);
            }
        }

        //子sku相同的产品库存检验;N-475
//        $setProductArray = array();
//        foreach ($products as $key=> $product) {
//            if($product['product_type'] == 0) {
//                //普通产品
//                //获取产品的combo组成
//                if(in_array($product['type_id'],[0,1])) {
//                    //非保证金产品，保证金产品库存是在锁定的部分
//                    if ($product['combo'] == 1) {
//                        $comboInfo = $this->model_buyer_buyer_common->getComboInfoByProductId($product['product_id'], $product['quantity']);
//                        $setProductArray = array_merge($setProductArray, $comboInfo);
//                    } else {
//                        $productInfo = array();
//                        $productInfo[] = array(
//                            'set_product_id' => $product['product_id'],
//                            'qty' => $product['quantity']
//                        );
//                        $setProductArray = array_merge($setProductArray, $productInfo);
//                    }
//                }
//            }else{
//                //保证金头款产品
//                if($product['type_id'] == 2 ){
//                    //现货保证金
//                    $marginProduct = $this->model_buyer_buyer_common->getMarginProduct($product['agreement_id']);
//                    if($marginProduct['combo_flag'] == 1 ){
//                        $comboInfo = $this->model_buyer_buyer_common->getComboInfoByProductId($marginProduct['product_id'], $marginProduct['num']);
//                        $setProductArray = array_merge($setProductArray, $comboInfo);
//                    }else{
//                        $productInfo = array();
//                        $productInfo[] = array(
//                            'set_product_id' => $marginProduct['product_id'],
//                            'qty' => $product['quantity']
//                        );
//                        $setProductArray = array_merge($setProductArray, $productInfo);
//                    }
//                    //现货保证金购买
//                }
//                if($product['type_id'] == 3 ){
//                    //期货保证金
//                }
//            }
//        }
//        //合并相同的key
//        $tempArr = array();
//        foreach ($setProductArray as $setProduct){
//            if($tempArr[$setProduct['set_product_id']]){
//                $tempArr[$setProduct['set_product_id']] = $tempArr[$setProduct['set_product_id']] + $setProduct['qty'];
//            }else{
//                $tempArr[$setProduct['set_product_id']] = $setProduct['qty'];
//            }
//        }
//        foreach ($tempArr as $key=>$setProductInfo){
//            //查询子sku的库存数
//            $result = $this->model_buyer_buyer_common->getBatchByProductId($key);
//            //查询sku的被lock数量
//            $lockResult = $this->model_buyer_buyer_common->getLockQtyByProductId($key);
//            if($setProductInfo>($result['qty']- $lockResult['lock_qty'])){
//                $this->log->write("创建订单库存不足:product_id=".$key.",购买数量:".$setProductInfo.",剩余数量:".($result['qty']- $lockResult['lock_qty']));
//                session()->set('error', "Product not available in the desired quantity or not in stock!Please contact with our customer service to argue. ");
//                $json['error']['url'] = $this->url->link('checkout/cart');
//                break;
//            }
//        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function save() {
        $this->load->language('checkout/checkout');
        $this->load->model('checkout/pay');

        $json = array();

        $payMethods = session('payment_methods');
        $payMethod = get_value_or_default($this->request->post,'payment_method', '');
        $orderId = get_value_or_default($this->request->post,'order_id', 0);

        if (empty($payMethod) || !isset($payMethods[$payMethod]))
        {
            $json['error']['warning'] = $this->language->get('error_payment');
        }

        if ($payMethod == PayCode::PAY_LINE_OF_CREDIT) {
            // 支付方式如果是信用额度，需要判断信用额度余额
            $total = (double)$this->model_checkout_pay->getOrderTotal($orderId);
            $lineOfCredit = (double)$this->customer->getLineOfCredit();
            if (bccomp($total, $lineOfCredit, 2) > 0) {
                $json['error']['warning'] = "Insufficient credit limit, please choose another payment method";
            }
        }
        if ($this->config->get('config_checkout_id')) {
            $this->load->model('catalog/information');

            $information_info = $this->model_catalog_information->getInformation($this->config->get('config_checkout_id'));

            if ($information_info && !isset($this->request->post['agree'])) {
                $json['error']['warning'] = sprintf($this->language->get('error_agree'), $information_info['title']);
            }
        }

        if (!$json) {
            session()->set('payment_method', $this->session->data['payment_methods'][$this->request->post['payment_method']]);

            session()->set('comment', strip_tags($this->request->post['comment']));
            //支付方式
            $json['payment_method'] = $this->request->post['payment_method'];
        }
        // 判断余额使用
        if (isset($this->session->data['useBalance'])) {
            $balance = session('useBalance');
            // 获取当前用户信用额度，判断传递的使用余额是否合法
            $lineOfCredit = $this->customer->getLineOfCredit();
            if (bccomp($balance, $this->cart->getQuoteTotal(), 2) > 0) {
                session()->set('error', 'The product price has changed, please checkout again.');
                $json['error']['url'] = $this->url->link('checkout/cart');
            }
            if (bccomp($balance, $lineOfCredit, 2) > 0) {
                // 支付的余额大于实际信用额度，页面重定向
                $this->response->redirect($this->url->link('checkout/cart'));
                // 余额大于订单总金额
            } else {
                // 保存Session信息
                session()->set('useBalance', $balance);
            }
        }

        //子sku相同的产品库存检验;N-475
//        $setProductArray = array();
//        foreach ($products as $key=> $product) {
//            if($product['product_type'] == 0) {
//                //普通产品
//                //获取产品的combo组成
//                if(in_array($product['type_id'],[0,1])) {
//                    //非保证金产品，保证金产品库存是在锁定的部分
//                    if ($product['combo'] == 1) {
//                        $comboInfo = $this->model_buyer_buyer_common->getComboInfoByProductId($product['product_id'], $product['quantity']);
//                        $setProductArray = array_merge($setProductArray, $comboInfo);
//                    } else {
//                        $productInfo = array();
//                        $productInfo[] = array(
//                            'set_product_id' => $product['product_id'],
//                            'qty' => $product['quantity']
//                        );
//                        $setProductArray = array_merge($setProductArray, $productInfo);
//                    }
//                }
//            }else{
//                //保证金头款产品
//                if($product['type_id'] == 2 ){
//                    //现货保证金
//                    $marginProduct = $this->model_buyer_buyer_common->getMarginProduct($product['agreement_id']);
//                    if($marginProduct['combo_flag'] == 1 ){
//                        $comboInfo = $this->model_buyer_buyer_common->getComboInfoByProductId($marginProduct['product_id'], $marginProduct['num']);
//                        $setProductArray = array_merge($setProductArray, $comboInfo);
//                    }else{
//                        $productInfo = array();
//                        $productInfo[] = array(
//                            'set_product_id' => $marginProduct['product_id'],
//                            'qty' => $product['quantity']
//                        );
//                        $setProductArray = array_merge($setProductArray, $productInfo);
//                    }
//                    //现货保证金购买
//                }
//                if($product['type_id'] == 3 ){
//                    //期货保证金
//                }
//            }
//        }
//        //合并相同的key
//        $tempArr = array();
//        foreach ($setProductArray as $setProduct){
//            if($tempArr[$setProduct['set_product_id']]){
//                $tempArr[$setProduct['set_product_id']] = $tempArr[$setProduct['set_product_id']] + $setProduct['qty'];
//            }else{
//                $tempArr[$setProduct['set_product_id']] = $setProduct['qty'];
//            }
//        }
//        foreach ($tempArr as $key=>$setProductInfo){
//            //查询子sku的库存数
//            $result = $this->model_buyer_buyer_common->getBatchByProductId($key);
//            //查询sku的被lock数量
//            $lockResult = $this->model_buyer_buyer_common->getLockQtyByProductId($key);
//            if($setProductInfo>($result['qty']- $lockResult['lock_qty'])){
//                $this->log->write("创建订单库存不足:product_id=".$key.",购买数量:".$setProductInfo.",剩余数量:".($result['qty']- $lockResult['lock_qty']));
//                session()->set('error', "Product not available in the desired quantity or not in stock!Please contact with our customer service to argue. ");
//                $json['error']['url'] = $this->url->link('checkout/cart');
//                break;
//            }
//        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }



}
