<?php

use App\Enums\FeeOrder\FeeOrderStatus;
use App\Enums\Order\OcOrderStatus;
use App\Logging\Logger;
use App\Repositories\FeeOrder\FeeOrderRepository;

/**
 * @property ModelCheckoutOrder $model_checkout_order
 * @property ModelCheckoutPay $model_checkout_pay
 * @property ModelCheckoutSuccess $model_checkout_success
 * @property ModelCheckoutUmfOrder $model_checkout_umf_order
 * */
class ControllerExtensionPaymentUmfPay extends Controller
{
    const PURCHASE_ORDER_TYPE = 1;
    const FEE_ORDER_TYPE = 2;
    public function index()
    {
        $data['order_id'] = session('order_id');
        $data['query'] = $this->url->link('extension/payment/umf_pay/query', '', true);
        $data['success'] = $this->url->link('checkout/success', '', true);
        return $this->load->view('extension/payment/umf_pay', $data);
    }

    public function confirm()
    {
        $json = array();
        if ($this->session->data['payment_method']['code'] == 'umf_pay') {
            $flag = false;
            //判断订单失效时间
            $this->load->model('checkout/order');
            $intervalTime = $this->model_checkout_order->checkOrderExpire($this->session->data['order_id']);
            $orderResult = $this->model_checkout_order->getOrderStatusByOrderId($this->session->data['order_id']);
            $order_status = $orderResult['order_status_id'];
            if($intervalTime>$this->config->get('expire_time')){
                //TODO 更新订单状态,预扣库存回退
                if($order_status != 7){
                    $this->model_checkout_order->cancelPurchaseOrderAndReturnStock($this->session->data['order_id']);
                }
                $flag = true;
                $json["status"] = "0";
                $json["msg"] = "The order has expired, please buy again!";
            }
            if (!$flag) {
                $this->load->model('checkout/umf_order');
                $json = $this->model_checkout_umf_order->createPayment($this->session->data['order_id'], $this->config->get('payment_umf_pay_order_status_id'));
            }

        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function query()
    {
        $result = $this->queryPayment();
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($result));
    }

//http://localhost/yzc/index.php?route=extension/payment/umf_pay/queryPayment
    public function queryPayment()
    {
        $json = array(
            'msg' => 'Trade does not exist',
            'status' => 0,
        );
        $productOrderId = $this->request->get('order_id', 0);
        $feeOrderIdStr = $this->request->get('fee_order_id',null);
        $feeOrderIdArr = $feeOrderIdStr == null ? [] :explode(',',$feeOrderIdStr);
        $this->load->model('checkout/order');
        $this->load->model('checkout/success');
        if(!empty($productOrderId)){
            $orderStatus = $this->model_checkout_success->getOrderStatus($productOrderId);
            if ($orderStatus == FeeOrderStatus::COMPLETE) {
                return array('status' => 1, 'msg' => 'Trade success');
            }else {
                $paymentInfos = $this->model_checkout_order->getPaymentInfo($productOrderId,static::PURCHASE_ORDER_TYPE);
                $json['paid'] = false;
                $json['status'] = 0;
                foreach ($paymentInfos as $paymentInfo){
                    $payId = $paymentInfo->pay_id;
                    $userId = $paymentInfo->user_id;
                    $paymentInfoId = $paymentInfo->id;
                    $status = $paymentInfo->status;
                    if (isset($payId)) {
                        $url = "/payments/payment/" . $payId;
                        $pay_result = $this->commonFunction->sendMsg($url, 'GET', $productOrderId);
                        $trade_result = $pay_result['payment']['state'];
                        if ($trade_result == 'TRADE_SUCCESS') {
                            if ($status != 201) {
                                //付款已成功  更改B2B订单状态
                                $this->model_checkout_order->updatePayment($payId);
                                $data = [
                                    'order_id' => $productOrderId,
                                    'fee_order_arr' => $feeOrderIdArr,
                                    'customer_id' => $userId,
                                    'payment_method' => $paymentInfo->pay_method,
                                    'payment_id' => $paymentInfoId
                                ];
                                $this->model_checkout_order->processingUmfOrderCompleted($data);
                            }
                            $json['status'] = 1;
                            break;
                        }
                    }
                }
            }
        }else if(!empty($feeOrderIdArr)){
            $feeOrderInfos = app(FeeOrderRepository::class)->findFeeOrderInfo($feeOrderIdArr);
            if ($feeOrderInfos[0]['status'] == FeeOrderStatus::COMPLETE) {
                return array('status' => 1, 'msg' => 'Trade success');
            }else {
                $paymentInfos = $this->model_checkout_order->getPaymentInfo($feeOrderInfos[0]['id'],static::FEE_ORDER_TYPE);
                $json['paid'] = false;
                $json['status'] = 0;
                foreach ($paymentInfos as $paymentInfo){
                    $payId = $paymentInfo->pay_id;
                    $userId = $paymentInfo->user_id;
                    $paymentInfoId = $paymentInfo->id;
                    $status = $paymentInfo->status;
                    if (isset($payId)) {
                        $url = "/payments/payment/" . $payId;
                        $pay_result = $this->commonFunction->sendMsg($url, 'GET', $feeOrderIdStr);
                        $trade_result = $pay_result['payment']['state'];
                        if ($trade_result == 'TRADE_SUCCESS') {
                            if ($status != 201) {
                                //付款已成功  更改B2B订单状态
                                $this->model_checkout_order->updatePayment($payId);
                                $data = [
                                    'order_id' => $productOrderId,
                                    'fee_order_arr' => $feeOrderIdArr,
                                    'customer_id' => $userId,
                                    'payment_method' => $paymentInfo->pay_method,
                                    'payment_id' => $paymentInfoId
                                ];
                                $this->model_checkout_order->processingUmfOrderCompleted($data);
                            }
                            $json['status'] = 1;
                            break;
                        }
                    }
                }
            }
        }
        return $json;
    }

    public function callbackByYzcm()
    {
        $data = html_entity_decode($this->request->post['data'], ENT_QUOTES);
        $this->log->write('[callbackByYzcm]回调订单 ' . $data);
        $data = json_decode($data, true);
        $this->load->model('checkout/order');
        foreach ($data as $item) {
            if (isset($item['state']) && $item['state'] == 'TRADE_SUCCESS') {
                //付款已成功  更改B2B订单状态
                $data = [
                    'order_id' => $item['yzc_order_id'] ?? '',
                    'fee_order_arr' => [],
                    'customer_id' => $item['customer_id'],
                    'payment_method' => $item['pay_method'],
                    'payment_id' => $item['payment_id'],
                ];
                $this->model_checkout_order->processingUmfOrderCompleted($data);
            }
        }
    }

    public function callbackByUmpay()
    {
        $input = file_get_contents("php://input");
        $data = json_decode($input, true);
        $this->log->write('[callbackByUmpay] ' . $input);
        if (!empty($data)) {
            $payment = $data['payment'];
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
                                pay_result='" . $this->db->escape($input) . "' ";
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

    /**
     * 处理老数据
     * http://localhost/index.php?route=extension/payment/umf_pay/olddata
     */
    public function olddata()
    {
        try {
            $this->orm->getConnection()->beginTransaction();
            $rows = $this->orm->table('tb_payment_info')->whereRaw('pay_method is null or pay_method=""')->get()->all();
            $rows = obj2array($rows);
            foreach ($rows as $row) {
                $pay_req = json_decode($row['pay_req'], true);
                $pay_result = json_decode($row['pay_result'], true);
                $updateRow['pay_method'] = 'umf_pay';
                $updateRow['pay_req'] = json_encode((object)['url' => $pay_req['links'][6]['href']]);
                $updateRow['pay_id'] = $pay_req['payment']['id'];
                $updateRow['pay_result'] = json_encode($pay_result['payment']);

                $this->orm->table('tb_payment_info')
                    ->where([['id', '=', $row['id']]])
                    ->update($updateRow);
            }
            $this->orm->getConnection()->commit();
            $this->response->setOutput('success');
        }catch (Exception $e){
            $this->orm->getConnection()->rollBack();
            $this->response->setOutput($e->getMessage());

        }

    }

    public function confirmNew($data)
    {
        $json = array();
        $orderId = $data['order_id'];
        $feeOrderIdArr = $data['fee_order_id'];
        if ($data['payment_code'] == 'umf_pay' && (!empty($orderId) || !empty($feeOrderIdArr))) {
            //判断订单失效时间
            $this->load->model('checkout/order');
            $this->load->model('checkout/pay');
            if(!empty($orderId)){
                $intervalTime = $this->model_checkout_order->checkOrderExpire($orderId);
                $orderResult = $this->model_checkout_order->getOrderStatusByOrderId($orderId);
                $order_status = $orderResult['order_status_id'];
                if($intervalTime>$this->config->get('expire_time') || $order_status != FeeOrderStatus::WAIT_PAY){
                    $json["status"] = "0";
                    $json["msg"] = "The order has expired, please buy again!";
                    return $json;
                }
            }
            if(!empty($feeOrderIdArr)) {
                $feeOrderInfos = app(FeeOrderRepository::class)->findFeeOrderInfo($feeOrderIdArr);
                $feeOrderStatus = $feeOrderInfos[0]['status'];
                $date_add = $feeOrderInfos[0]['created_at'];
                $feeOrderIntervalTime = (time()-strtotime($date_add))/60;
                if($feeOrderIntervalTime>$this->config->get('expire_time') || $feeOrderStatus != FeeOrderStatus::WAIT_PAY){
                    $json["status"] = "0";
                    $json["msg"] = "The order has expired, please buy again!";
                    return $json;
                }
            }

                $this->load->model('checkout/umf_order');
                try {
                    $this->db->beginTransaction();
                    $json = $this->model_checkout_umf_order->createPayment($data);
                    if($json['status'] !=1){
                        Logger::error("UMF_PAY支付失败,order_id=".$data['order_id'].','.$json['msg']);
                        $this->db->rollback();
                    }
                    $this->db->commit();
                }catch (Exception $e){
                    Logger::error("UMF_PAY支付异常,order_id=".$data['order_id'].','.$e->getMessage());
                    $this->db->rollback();
                }
            }
        return $json;
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
                        $paymentInfoId = $paymentInfo->id;
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
                                    'payment_id' => $paymentInfoId
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
                        $paymentInfoId = $paymentInfo->id;
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
                                    'payment_id' => $paymentInfoId
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

