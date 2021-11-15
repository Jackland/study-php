<?php

use App\Logging\Logger;
use App\Repositories\SalesOrder\AutoBuyRepository;
use App\Services\FeeOrder\FeeOrderService;
use App\Services\FeeOrder\StorageFeeService;

/**
 * @property ModelExtensionModulePrice $model_extension_module_price
 * @property ModelApiAutoBuy $model_api_auto_buy
 * @property ModelSettingExtension $model_setting_extension
 * */
class ControllerApiCart extends Controller
{
    const START_API_ADD_CART = '----API ADD CART START----';
    const END_API_ADD_CART = '----API ADD CART END----';

    /**
     * @deprecated 走新的自动购买逻辑
     * @see ControllerApiAutoPurchase::index() 入口
     * @see AddCartComponent::handle() 购买逻辑
     */
    public function add()
    {

        // java 传参 item_code & quantity & buyer_id & sales_line_id
        Logger::order(static::START_API_ADD_CART, 'info', [
            Logger::CONTEXT_WEB_SERVER_VARS => ['_POST', '_GET'],
        ]);

        $this->load->language('api/cart');
        $this->load->model('extension/module/price');
        $this->load->model('api/auto_buy');

        $priceModel = $this->model_extension_module_price;
        $json = array();
        //需要传入buyer
        $buyer_id = $this->request->post['buyer_id'];
        session()->set('customer_id', $buyer_id);
        //新增一个sales_line_id,销售订单明细主键ID
        $sales_line_id = $this->request->post('sales_line_id',0);
        $customer = new Cart\Customer($this->registry);
        $this->registry->set('customer', $customer);
        if (!isset($this->session->data['api_id'])) {
            $json['error']['warning'] = $this->language->get('error_permission');
        } else if (!isset($buyer_id)) {
            $json['error']['warning'] = $this->language->get('error_buyer_required');
        } else if (!isset($this->request->post['quantity'])){
            $json['error']['warning'] = $this->language->get('error_quantity_required');
        } else if (!isset($this->request->post['item_code'])){
            $json['error']['warning'] = $this->language->get('error_item_code_required');
        } else {
            $item_code = $this->request->post['item_code'];
            $check_sales = $this->model_api_auto_buy->checkSalesOrder($buyer_id, $sales_line_id);
            $quantity = get_value_or_default($this->request->post,'quantity', 0);
            $seller_id = get_value_or_default($this->request->post, 'seller_id', 0);
            if (!$check_sales || !$quantity){
                Logger::error(['check sales', 'error',
                    Logger::CONTEXT_VAR_DUMPER => [
                        'check_sales' => $check_sales,
                        'quantity' => $quantity,
                    ], // 按照可视化形式输出
                ]);
                $json['error']['warning'] = 'Parameter error';
            }

            $item_code = $priceModel->verifyAutoBuyItemCode($item_code);
            if ($item_code) {
                $this->load->model('catalog/product');
                // 加入购物车购买失败最多的场景，此处在priceModel内部增加log，方便排查问题
                $product_info = $priceModel->getAutoBuyProductId($item_code,$buyer_id,$quantity,$seller_id);
                if (isset($this->request->post['option'])) {
                    $option = array_filter($this->request->post['option']);
                } else {
                    $option = [];
                }

                if(is_string($product_info)){
                    $json['error']['option'] = 'Sales order line id is '.$sales_line_id.','.$product_info;
                }
                // 判断能否添加到购物车
                if (!isset($json['error']) && !app(AutoBuyRepository::class)->canAddCart($product_info, $buyer_id, $sales_line_id, $quantity)) {
                    $json['error']['warning'] = 'Purchase quantity overflow';
                }
                if (!isset($json['error'])) {
                    //此处出现过程序异常，主键冲突。需要对异常捕获，并返回错误信息。（因为网络问题，没有进行后续的支付环节，导致一个buyer的商品在购物车中没有清空，唯一性约束冲突）
                    $homePick = $this->customer->isCollectionFromDomicile();
                    $this->session->data['delivery_type'] = $deliveryType = $homePick? 1:0 ;
                    $this->cart->addWithBuyerId($product_info['product_id'],$buyer_id, $quantity, $option,0,$product_info['type_id'],$product_info['agreement_id'],$deliveryType);

                    //购物车添加成功后需要更新sales_line_id对应的销售订单明细的ItemCode为此次加入购物车生效的SKU
                    $this->model_api_auto_buy->updateSalesItemCode($sales_line_id,$item_code);

                    $json['success'] = $this->language->get('text_success');
                }

            }else {
                // 更新item_code 无法加入购物车的报错
                $json['error']['store'] = sprintf($this->language->get('error_no_product_id'),
                    $sales_line_id,$this->request->post['item_code']);
            }
        }
        if(isset($json['error'])){
            $this->cart->clearWithBuyerId($buyer_id);
            //清除comboInfo和关联关系
            $check_sales = $this->model_api_auto_buy->checkSalesOrder($buyer_id, $sales_line_id);
            if($check_sales) {
                $salesOrderId = $this->cart->getSalesOrderId($sales_line_id);
                $this->cart->removeAssociateAndComboInfo($salesOrderId);
                //解绑仓租，取消费用单
                app(StorageFeeService::class)->unbindBySalesOrder([$salesOrderId]);
                $feeOrderStr = $this->request->post('fee_order_list',null);
                $feeOrderArr = ($feeOrderStr == null)?[]:explode(',',$feeOrderStr);
                $feeOrderService = app(FeeOrderService::class);
                foreach ($feeOrderArr as $feeOrderId) {
                    $feeOrderService->changeFeeOrderStatus($feeOrderId, 7);
                }
            }
        }
        // 记录当前购物车中的所有内容
        $cartInfo = db('oc_cart')->where('customer_id',$buyer_id)->get();
        Logger::order(['api/cart/add', 'info',
            Logger::CONTEXT_VAR_DUMPER => ['cartInfo' => $cartInfo ],
        ]);
        // 记录返回成功还是失败的
        Logger::order([static::END_API_ADD_CART, 'info',
            Logger::CONTEXT_VAR_DUMPER => ['json' => $json ],
        ]);
        return $this->response->json($json);
    }

    public function edit()
    {
        $this->load->language('api/cart');

        $json = array();

        if (!isset($this->session->data['api_id'])) {
            $json['error'] = $this->language->get('error_permission');
        } else {
            $this->cart->update($this->request->post['key'], $this->request->post['quantity']);

            $json['success'] = $this->language->get('text_success');

            $this->session->remove('shipping_method');
            $this->session->remove('shipping_methods');
            $this->session->remove('payment_method');
            $this->session->remove('payment_methods');
            $this->session->remove('reward');
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function remove()
    {
        $this->load->language('api/cart');

        $json = array();

        if (!isset($this->session->data['api_id'])) {
            $json['error'] = $this->language->get('error_permission');
        } else {
            // Remove
            if (isset($this->request->post['key'])) {
                $this->cart->remove($this->request->post['key']);

                $this->session->removeDeepByKey('vouchers', $this->request->post['key']);

                $json['success'] = $this->language->get('text_success');

                $this->session->remove('shipping_method');
                $this->session->remove('shipping_methods');
                $this->session->remove('payment_method');
                $this->session->remove('payment_methods');
                $this->session->remove('reward');
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function products()
    {
        $this->load->language('api/cart');

        $json = array();

        if (!isset($this->session->data['api_id'])) {
            $json['error']['warning'] = $this->language->get('error_permission');
        } else {
            // Stock
            if (!$this->cart->hasStock() && (!$this->config->get('config_stock_checkout') || $this->config->get('config_stock_warning'))) {
                $json['error']['stock'] = $this->language->get('error_stock');
            }

            // Products
            $json['products'] = array();

            $products = $this->cart->getProducts();

            foreach ($products as $product) {
                $product_total = 0;

                foreach ($products as $product_2) {
                    if ($product_2['product_id'] == $product['product_id']) {
                        $product_total += $product_2['quantity'];
                    }
                }

                if ($product['minimum'] > $product_total) {
                    $json['error']['minimum'][] = sprintf($this->language->get('error_minimum'), $product['name'], $product['minimum']);
                }

                $option_data = array();

                foreach ($product['option'] as $option) {
                    $option_data[] = array(
                        'product_option_id' => $option['product_option_id'],
                        'product_option_value_id' => $option['product_option_value_id'],
                        'name' => $option['name'],
                        'value' => $option['value'],
                        'type' => $option['type']
                    );
                }

                $json['products'][] = array(
                    'cart_id' => $product['cart_id'],
                    'product_id' => $product['product_id'],
                    'name' => $product['name'],
                    'model' => $product['model'],
                    'option' => $option_data,
                    'quantity' => $product['quantity'],
                    'stock' => $product['stock'] ? true : !(!$this->config->get('config_stock_checkout') || $this->config->get('config_stock_warning')),
                    'shipping' => $product['shipping'],
                    'price' => $this->currency->format($this->tax->calculate($product['price'], $product['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']),
                    'total' => $this->currency->format($this->tax->calculate($product['price'], $product['tax_class_id'], $this->config->get('config_tax')) * $product['quantity'], $this->session->data['currency']),
                    'reward' => $product['reward']
                );
            }

            // Voucher
            $json['vouchers'] = array();

            if (!empty($this->session->data['vouchers'])) {
                foreach ($this->session->data['vouchers'] as $key => $voucher) {
                    $json['vouchers'][] = array(
                        'code' => $voucher['code'],
                        'description' => $voucher['description'],
                        'from_name' => $voucher['from_name'],
                        'from_email' => $voucher['from_email'],
                        'to_name' => $voucher['to_name'],
                        'to_email' => $voucher['to_email'],
                        'voucher_theme_id' => $voucher['voucher_theme_id'],
                        'message' => $voucher['message'],
                        'price' => $this->currency->format($voucher['amount'], $this->session->data['currency']),
                        'amount' => $voucher['amount']
                    );
                }
            }

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

            foreach ($totals as $key => $value) {
                $sort_order[$key] = $value['sort_order'];
            }

            array_multisort($sort_order, SORT_ASC, $totals);

            $json['totals'] = array();

            foreach ($totals as $total) {
                $json['totals'][] = array(
                    'title' => $total['title'],
                    'text' => $this->currency->format($total['value'], $this->session->data['currency'])
                );
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
}
