<?php

/**
 * Created by IntelliJ IDEA.
 * User: chenyang
 * Date: 2019/5/30
 * Time: 14:21
 * @property ModelAccountDeliverySignature $model_account_deliverySignature
 * @property ModelToolImage $model_tool_image
 */
class ControllerAccountDeliverySignature extends Controller
{
    public function index()
    {
        if (!$this->customer->isLogged()) {
            session()->set('redirect', $this->url->link('account/deliverySignature', '', true));

            $this->response->redirect($this->url->link('account/login', '', true));
        }
        $header_id = $this->request->get['id'];
        $customer_id = $this->customer->getId();
        $country_id = $this->customer->getCountryId();

        $data['breadcrumbs'] = array();
        $this->load->language('account/customer_order');
        $this->load->language('account/deliverySignature');
        $this->document->setTitle($this->language->get('heading_title_delivery_signature'));

        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/home')
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_account'),
            'href' => $this->url->link('account/account', '', true)
        );
        if($this->customer->isCollectionFromDomicile()){
            $data['breadcrumbs'][] = array(
                'text' => $this->language->get('text_customer_order'),
                'href' => $this->url->link('account/customer_order', '', true)
            );
        }else{
            $data['breadcrumbs'][] = array(
                'text' => $this->language->get('text_customer_order'),
                'href' => $this->url->link('account/sales_order/sales_order_management', '', true)
            );
        }
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_delivery_signature'),
            'href' => $this->url->link('account/deliverySignature&id=' . $header_id, '', true)
        );

        $this->load->model('account/deliverySignature');
        $this->load->model('tool/image');

        $delivery_list = array();
        $sum_package_qty = 0;
        $sum_ds_fee = 0;
        $order_id = '';
        if (!isset($customer_id) || !isset($header_id) || !isset($country_id)) {
            $data['summary_notice'] = $this->language->get('error_ds_invalid_param');
        } else {
            $oldest_asr = $this->model_account_deliverySignature->getOldestAsrOrder($customer_id);
            if(!isset($oldest_asr) || empty($oldest_asr)){
                $data['summary_notice'] = $this->language->get('error_ds_invalid_param');
            }elseif ($oldest_asr['id'] != $header_id){
                $data['summary_notice'] = sprintf($this->language->get('error_ds_older_order'),$oldest_asr['order_id']);
            }else{
                $result = $this->model_account_deliverySignature->getDeliverySignatureByHeaderId($header_id, $customer_id);
                $ds_product = $this->model_account_deliverySignature->getDeliverySignatureProduct($country_id);

                if (isset($result) && !empty($result) && isset($ds_product) && !empty($ds_product)) {
                    $ds_unit_price = $ds_product['price'];
                    //以productId分组
                    foreach ($result as $information) {
                        $line_id = $information['lineId'];
                        if (isset($delivery_list[$line_id])) {
                            $new_product_qty = intval($information['qty']) + $delivery_list[$line_id]['qty'];
                            $delivery_list[$line_id]['qty'] = $new_product_qty;
                            if (isset($information['setQty'])) {
                                $new_package_qty = intval($information['qty']) * intval($information['setQty']) + $delivery_list[$line_id]['packageQty'];
                                $delivery_list[$line_id]['packageQty'] = $new_package_qty;
                            } else {
                                $new_package_qty = intval($information['qty']) + $delivery_list[$line_id]['packageQty'];
                                $delivery_list[$line_id]['packageQty'] = $new_package_qty;
                            }
                        } else {
                            $order_id = $information['order_id'];
                            $image = $this->model_tool_image->resize($information['image'], $this->config->get('theme_' . $this->config->get('config_theme') . '_image_cart_width'), $this->config->get('theme_' . $this->config->get('config_theme') . '_image_cart_height'));
                            if (isset($information['setQty'])) {
                                $package_qty = intval($information['qty']) * intval($information['setQty']);
                            } else {
                                $package_qty = intval($information['qty']);
                            }

                            $delivery_list[$line_id] = array(
                                'itemCode' => $information['sku'],
                                'image' => $image,
                                'productName' => $information['name'],
                                'url' => $this->url->link('product/product', 'product_id=' . $information['product_id']),
                                'qty' => $information['qty'],
                                'packageQty' => $package_qty
                            );
                        }
                    }
                    $unit_price = $this->currency->format($ds_unit_price, $this->session->data['currency']);

                    foreach ($delivery_list as $key => $value) {
                        $ds_fee = doubleval($ds_unit_price) * intval($value['packageQty']);
                        $delivery_list[$key]['detailUnitPrice'] = $unit_price;
                        $delivery_list[$key]['detailTotalPrice'] = $this->currency->format($ds_fee, $this->session->data['currency']);

                        $sum_package_qty += $value['packageQty'];
                        $sum_ds_fee += $ds_fee;
                    }

                    $delivery_brief = array(
                        'product_id' => $ds_product['product_id'],
                        'itemCode' => $ds_product['sku'],
                        'productName' => $ds_product['name'],
                        'storeName' => $ds_product['screenname'],
                        'unitPrice' => $unit_price,
                        'qty' => $sum_package_qty,
                        'totalFee' => $this->currency->format($sum_ds_fee, $this->session->data['currency'])
                    );
                    $data['delivery_brief'] = $delivery_brief;
                    $data['delivery_list'] = $delivery_list;

                    $data['summary_notice'] = sprintf($this->language->get('text_ds_summary'), $order_id, $this->currency->format($sum_ds_fee, $this->session->data['currency']));
                    $data['checkout'] = $this->url->link('checkout/checkout', '', true);
                } else {
                    $data['summary_notice'] = $this->language->get('error_ds_no_record');
                }
            }
        }

        $data['footer'] = $this->load->controller('common/footer');
        $data['header'] = $this->load->controller('common/header');
        $this->response->setOutput($this->load->view('account/deliverySignature', $data));
    }

    //清空现有购物车
    public function preCheckout()
    {
        $customer_id = $this->customer->getId();
        if (!$this->customer->isLogged() || !isset($customer_id)) {
            session()->set('redirect', $this->url->link('account/customer_order', '', true));

            $this->response->redirect($this->url->link('account/login', '', true));
        }

        //清空当前购物车
        $this->cart->clearWithBuyerId($customer_id);
    }
}