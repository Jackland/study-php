<?php

use App\Enums\FeeOrder\FeeOrderStatus;
use App\Enums\Order\OcOrderStatus;
use App\Enums\Pay\PayCode;
use App\Logging\Logger;
use App\Repositories\FeeOrder\FeeOrderRepository;

/**
 * @package        OpenCart
 * @author        Meng Wenbin
 * @copyright    Copyright (c) 2010 - 2017, Chengdu Guangda Network Technology Co. Ltd. (https://www.opencart.cn/)
 * @license        https://opensource.org/licenses/GPL-3.0
 * @link        https://www.opencart.cn
 * @property ModelCheckoutOrder $model_checkout_order
 * @property ModelCheckoutPay $model_checkout_pay
 * @property ModelCheckoutSuccess$model_checkout_success
 */
class ControllerExtensionPaymentWechatPay extends Controller
{
    const PURCHASE_ORDER_TYPE = 1;
    const FEE_ORDER_TYPE = 2;
    public function index()
    {
        $data['order_id'] = session('order_id');
        $interval = $this->config->get('payment_wechat_pay_interval') ? $this->config->get('payment_wechat_pay_interval') : 5;
        $data['interval'] = $interval * 1000;
        $data['success'] = $this->url->link('checkout/success', '', true);
        return $this->load->view('extension/payment/wechat_pay', $data);
    }

    /**
     * 微信支付与umf_pay不同  是在第二步点continue时创建订单
     * @throws Exception
     */
    public function createPayment()
    {
        $this->load->model('checkout/wechat_order');
        $this->load->model('checkout/pay');
        $payData['wechatName'] = $this->request->post('wechatName');
        $payData['wechatPhone'] = $this->request->post('wechatPhone');
        $payData['wechatIdCard'] = $this->request->post('wechatIdCard');
        $balance = $this->request->post('balance', 0);
        $payment_code = $this->request->post('payment_method', PayCode::PAY_LINE_OF_CREDIT);
        $order_id = $this->request->post('order_id', 0);
        $fee_order_id = $this->request->post('fee_order_id', 0);
        $comment = $this->request->post('comment', '');
        try {
            $this->db->beginTransaction();
            //修改订单信息
            $data = [
                'order_id' => $order_id,
                'fee_order_id' => $fee_order_id,
                'balance' => $balance,
                'payment_code' => $payment_code,
                'comment' => $comment,
                'pay_data' => $payData
            ];
            $json = $this->model_checkout_wechat_order->createPayment($data);
            $this->db->commit();
        }catch (Exception $e){
            $this->db->rollback();
        }
        $this->response->headers->set('Content-Type','application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function confirm()
    {
        $json = array();
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function callbackByUmpay()
    {
        $input = file_get_contents("php://input");
        $data = json_decode($input, true);
        if (!empty($data)) {
            $payment = $data['payment'];
            $paymentString = json_encode($payment);
            Logger::app('[callbackByUmpay][wechat] ' . $paymentString);
            $orderId = $payment['order']['mer_reference_id'];
            $totalCNY = $payment["order"]["amount"]['total_cny'];
            $payment_query = $this->db->query('select * from tb_payment_info where order_id =' . $orderId . ' limit 1')->row;
            $state_query = $this->db->query('select * from oc_umf_status where state =\'' . $payment['state'] . '\' limit 1')->row;
            $is_update = !empty($payment_query);
            if ($is_update) {
                $payment_sql = 'update tb_payment_info set ';
            } else {
                $payment_sql = 'insert into tb_payment_info set order_id=\'' . $orderId . '\', ';
            }
            $payment_sql .= " amount ='$totalCNY',
                                update_date=NOW(),
                                status={$state_query['code']},
                                pay_result='" . $this->db->escape($paymentString) . "' ";
            if ($is_update) {
                $payment_sql .= " where id={$payment_query['id']}";
            }
            $this->db->query($payment_sql);
            if ($payment['state'] == 'TRADE_SUCCESS') {
                $this->load->model('checkout/order');
                $data = $this->model_checkout_order->getPaymentInfoDetails($payment_query['id']);
                //付款已成功  更改B2B订单状态
                $this->model_checkout_order->processingUmfOrderCompleted($data);
            }
            $this->response->headers->set('Content-Type','application/json');
            $this->response->setOutput("{\"meta\":{\"ret_code\":\"0000\",\"ret_msg\":[\"SUCCESS\"]}}");
        }
    }


    public function isOrderPaid()
    {
        $json['paid'] = false;
        if (isset($this->request->get['order_id'])) {
            $order_id = $this->request->get['order_id'];
            $this->load->model('checkout/order');
            $order = $this->db->query('select order_status_id from oc_order where order_id=' . $this->db->escape($order_id))->row;
            if (isset($order['order_status_id']) && $order['order_status_id'] == OcOrderStatus::COMPLETED) {
                $json['paid'] = true;
            }
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function wechatShow()
    {
        $data['order_id'] = session('order_id');
        $interval = $this->config->get('payment_wechat_pay_interval') ? $this->config->get('payment_wechat_pay_interval') : 5;
        $data['interval'] = $interval * 1000;
        $data['success'] = $this->url->link('checkout/success', '', true);
        return $this->load->view('extension/payment/wechat_pay', $data);
    }

    public function checkOrder(){
        $this->load->model('checkout/order');
        $this->load->model('checkout/success');
        $json['paid'] = false;
        $productOrderId = $this->request->get('order_id', 0);
        $feeOrderIdStr = $this->request->get('fee_order_id',null);
        try {
            $feeOrderIdArr = $feeOrderIdStr == null ? [] :explode(',',$feeOrderIdStr);
            if(!empty($productOrderId)){
                $orderStatus = $this->model_checkout_success->getOrderStatus($productOrderId);
                if ($orderStatus == FeeOrderStatus::COMPLETE) {
                    $json['status'] = 1;
                    $json['paid'] = true;
                    $json['success'] = $this->url->link('checkout/success&o=' . $productOrderId.'&f='.$feeOrderIdStr, false);
                }else {
                    $paymentInfos = $this->model_checkout_order->getPaymentInfo($productOrderId,static::PURCHASE_ORDER_TYPE);
                    $json['paid'] = false;
                    $json['status'] = 0;
                    foreach ($paymentInfos as $paymentInfo){
                        $payId = $paymentInfo->pay_id;
                        $userId = $paymentInfo->user_id;
                        $paymentId = $paymentInfo->id;
                        if (isset($payId)) {
                            $url = "/payments/payment/" . $payId;
                            $pay_result = $this->commonFunction->sendMsg($url, 'GET', $productOrderId);
                            $trade_result = $pay_result['payment']['state'] ?? '';
                            if ($trade_result == 'TRADE_SUCCESS') {
                                $json['status'] = 1;
                                $json['paid'] = true;
                                //付款已成功  更改B2B订单状态
                                $this->model_checkout_order->updatePayment($payId);
                                $data = [
                                    'order_id' => $productOrderId,
                                    'fee_order_arr' => $feeOrderIdArr,
                                    'customer_id' => $userId,
                                    'payment_method' => $paymentInfo->pay_method,
                                    'payment_id' => $paymentId
                                ];
                                $this->model_checkout_order->processingUmfOrderCompleted($data);
                                $json['success'] = $this->url->link('checkout/success&o=' . $productOrderId.'&f='.$feeOrderIdStr, false);
                                break;
                            } else {
                                $json['status'] = 0;
                                $json['paid'] = false;
                            }

                        }
                    }
                }
            }else if(!empty($feeOrderIdArr)){
                $feeOrderInfos = app(FeeOrderRepository::class)->findFeeOrderInfo($feeOrderIdArr);
                if ($feeOrderInfos[0]['status'] == FeeOrderStatus::COMPLETE) {
                    $json['status'] = 1;
                    $json['paid'] = true;
                    $json['success'] = $this->url->link('checkout/success&o=' . $productOrderId.'&f='.$feeOrderIdStr, false);
                }else {
                    $paymentInfos = $this->model_checkout_order->getPaymentInfo($feeOrderInfos[0]['id'],static::FEE_ORDER_TYPE);
                    $json['paid'] = false;
                    $json['status'] = 0;
                    foreach ($paymentInfos as $paymentInfo){
                        $payId = $paymentInfo->pay_id;
                        $userId = $paymentInfo->user_id;
                        $paymentId = $paymentInfo->id;
                        if (isset($payId)) {
                            $url = "/payments/payment/" . $payId;
                            $pay_result = $this->commonFunction->sendMsg($url, 'GET', $feeOrderIdStr);
                            $trade_result = $pay_result['payment']['state'] ?? '';
                            if ($trade_result == 'TRADE_SUCCESS') {
                                $json['status'] = 1;
                                $json['paid'] = true;
                                //付款已成功  更改B2B订单状态
                                $this->model_checkout_order->updatePayment($payId);
                                $data = [
                                    'order_id' => $productOrderId,
                                    'fee_order_arr' => $feeOrderIdArr,
                                    'customer_id' => $userId,
                                    'payment_method' => $paymentInfo->pay_method,
                                    'payment_id' => $paymentId
                                ];
                                $this->model_checkout_order->processingUmfOrderCompleted($data);
                                $json['success'] = $this->url->link('checkout/success&o=' . $productOrderId.'&f='.$feeOrderIdStr, false);
                                break;
                            } else {
                                $json['status'] = 0;
                                $json['paid'] = false;
                            }

                        }
                    }
                }
            }
        }catch (Exception $e){
            $preMsg = $e->getMessage();
            $msg = "[UMF采购订单查询订单状态失败],采购订单ID：" . $productOrderId . ",umfPayMethod方法失败";
            $errorMsg = $msg.$preMsg;
            Logger::app($errorMsg);
            $json['status'] = 0;
            $json['paid'] = false;
        }
        $this->response->headers->set('Content-Type','application/json');
        $this->response->setOutput(json_encode($json));
    }
}
