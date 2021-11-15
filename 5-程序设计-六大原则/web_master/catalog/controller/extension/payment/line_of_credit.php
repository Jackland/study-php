<?php

use App\Enums\FeeOrder\FeeOrderStatus;
use App\Enums\Pay\PayCode;
use App\Exception\PaymentException;
use App\Logging\Logger;
use App\Repositories\FeeOrder\FeeOrderRepository;
use App\Services\Margin\MarginService;

/**
 * @property ModelCheckoutOrder $model_checkout_order
 * @property ModelCheckoutcrawlerorders $model_checkout_crawler_orders
 * @property ModelCheckoutPay $model_checkout_pay
 * */
class ControllerExtensionPaymentLineOfCredit extends Controller
{
    private $feeOrderRepository;
    public function __construct(Registry $registry, FeeOrderRepository $feeOrderRepository)
    {
        parent::__construct($registry);
        $this->feeOrderRepository = $feeOrderRepository;
    }
    public function index()
    {
        //3150 是否需要二级密码
        $data['second_passwd'] = boolval($this->customer->getCustomerExt(3));
        //end xxl
        return $this->load->view('extension/payment/line_of_credit',$data);
    }

    public function confirm()
    {
        $this->load->model('checkout/order');
        $json = array();
        $deleteOrderFlag = true;
//        $order_id = get_value_or_default($this->request->get, 'order_id', 0);
        $order_id = session('order_id');
        if(!empty($order_id)) {
            $orderInfo = $this->model_checkout_order->getOrder($order_id);
            if ($orderInfo['payment_code'] == PayCode::PAY_LINE_OF_CREDIT) {
                $this->load->model('checkout/order');
                $this->load->model('checkout/crawler_orders');
                // add by LiLei 调用 爬虫接口，如果接口返回正确继续后面的逻辑，否则给出前端的提示。
                $crawlerOrderResult = $this->model_checkout_crawler_orders->crawlerOrders($order_id, $this->config->get('payment_line_of_credit_order_status_id'));
                if ($crawlerOrderResult) {
                    $json["status"] = $crawlerOrderResult["status"];
                    $json["status"] = "2";
                    if ($crawlerOrderResult["status"] == "0") {
                        $json["msg"] = $crawlerOrderResult["msg"];
                        $deleteOrderFlag = true;
                    } else if ($crawlerOrderResult["status"] == "1") {
                        $json["redirect"] = $this->url->link('checkout/cart');
                        $json["data"] = $crawlerOrderResult["data"];
                        $deleteOrderFlag = true;
                    } else if ($crawlerOrderResult["status"] == "2") {
                        $deleteOrderFlag = false;
                    } else if ($crawlerOrderResult["status"] == "3") {
                        // 订单已提交
                        $deleteOrderFlag = true;
                        $json['redirect'] = $this->url->link('checkout/success');
                    }
                } else {
                    $deleteOrderFlag = true;
                    $json["status"] = "0";
                    $json["msg"] = "ERROR!";
                }

            }

            if (!$deleteOrderFlag) {
                // 购买订单成功
                $customer_id = $this->customer->getId();
                try {
                    $this->db->beginTransaction();
                    //查询订单状态+行锁
                    $orderResult = $this->getOrderStatusByOrderId($order_id);
                    $order_status = $orderResult['order_status_id'];
                    //获取订单失效时间
                    $intervalTime = $this->model_checkout_order->checkOrderExpire($order_id);
                    if ($order_status == 0 && $intervalTime < $this->config->get('expire_time')) {
                        $complete_status = 5;
                        //扣减信用额度
                        //$this->changeLineOfCredit();//不可用了
                        $this->model_checkout_order->payByLineOfCredit($order_id);
                        $this->model_checkout_order->addOrderHistoryByYzcModel($order_id, $complete_status);
                        $this->db->commit();
                        $this->model_checkout_order->addOrderHistoryByYzcModelAfterCommit($order_id);
                        $json['redirect'] = $this->url->link('checkout/success');
                    } else if ($order_status == 5) {
                        $json['status'] = 4;
                        $json['msg'] = "The order has been paid!";
                    } else {
                        // 更新订单状态,预扣库存回退
                        if ($order_status != 7) {
                            $this->model_checkout_order->cancelPurchaseOrderAndReturnStock($order_id);
                            $this->db->commit();
                        }
                        $json['status'] = "4";
                        $json['msg'] = "The order has expired, please buy again!";
                        $json['redirect'] = str_replace('&amp;', '&', $this->url->link('checkout/confirm/toPay', '&order_id='.$order_id));
                    }
                } catch (Exception $e) {
                    //回滚
                    $this->db->rollback();
                    $msg = "[dealPaymentCallback]
line_of_credit失败
customer_id:" . $customer_id . ",order_id:" . $order_id . "
errorMsg:" . $e->getMessage();

                    //记录日志
                    $this->log->write($msg);
                    //异常通知
                    $this->model_checkout_order->sendErrorMsg($customer_id, $order_id, PayCode::PAY_LINE_OF_CREDIT, $msg);

                    if (-1 == $e->getMessage()) {
                        $fail_msg = 'The available sales quantity cannot satisfy the requirements in the agreement.' . PHP_EOL . 'The transaction is failed. Please contact with the store to confirm. ';
                    } else {
                        $fail_msg = $this->config->get('payment_fail_msg');
                    }
                    if (empty($fail_msg)) {

                        $fail_msg = 'Payment failed, we will deal with it as soon as possible. If you have any questions, please contact us.';
                    }
                    $json['status'] = 0;
                    $json['msg'] = $fail_msg;
                }
            } else {
                $json['status'] = 4;
                $json['msg'] = $crawlerOrderResult['msg'];
                $json['redirect'] = str_replace('&amp;', '&', $this->url->link('checkout/confirm/toPay', '&order_id='.$order_id));
            }
        }else{
            $json['status'] = 2;
            session()->set('error', 'Payment failed, we will deal with it as soon as possible. If you have any questions, please contact us.');
            $this->log->write("订单支付order_id不存在");
            $json['redirect'] = $this->url->link('checkout/cart');
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }


    /**
     * 检验二级密码
     * @return array
     * @throws Exception
     */
    public function checkSecondPassword(){
        $this->load->model('checkout/order');
        if(!empty($this->request->request['secondPassword'])){
            $secondPassword = $this->request->request['secondPassword'];
            $password = $this->model_checkout_order->getSecondPassowrd($this->customer->getId());
            if(isset($password)){
                if (password_verify($secondPassword,$password->password)) {
                    $json['success'] = true;
                }else{
                    $json['success'] = false;
                    $json['text'] = 'Password input error!';
                }
            }else{
                $json['success'] = false;
                $json['text'] = 'Password input error!';
            }
        }else{
            $json['success'] = false;
            $json['text'] = 'Password input error!';
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * 扣减信用额度
     * @throws Exception
     */
    public function changeLineOfCredit(): void
    {
        //信用额度扣减(获取订单total)
        $lineOfCredit = $this->customer->getLineOfCredit();
        $totals = $this->model_checkout_order->getOrderTotals(session('order_id'));
        foreach ($totals as $total) {
            if ($total['code'] != 'total') {
                continue;
            } else {
                $total = $total['value'];
            }
        }
        if (isset($this->session->data['useBalance'])) {
            $useBalance = session('useBalance');
        } else {
            $useBalance = 0;
        }
        $creditChange = $lineOfCredit - $total - $useBalance;
        $this->model_checkout_order->changeLineOfCredit($creditChange,$this->customer->getId());
        $this->load->model('extension/payment/line_of_credit');
        $updateDate = array(
            'customerId' => $this->customer->getId(),
            'oldBalance' => $lineOfCredit,
            'balance' => $creditChange,
            'operatorId' => $this->customer->getId(),
            'typeId' => 2,
            'orderId' => session('order_id')
        );
        $this->model_extension_payment_line_of_credit->saveAmendantRecord($updateDate);
    }

    /**
     * 获取
     * @author xxl
     * @param int $order_id
     * @return array
     */
    private function getOrderStatusByOrderId($order_id){
        $result = $this->db->query('select order_status_id,date_added from oc_order where order_id='.intval($order_id).' for update')->row;
        return $result;
    }

    public function confirmNew($data)
    {
        $this->load->model('checkout/order');
        $this->load->model('checkout/pay');
        $json = array();
        $orderId = $data['order_id'];
        $feeOrderIdArr = $data['fee_order_id'];
        if (!empty($orderId) || !empty($feeOrderIdArr)) {
            // 购买订单成功
            $customerId = $this->customer->getId();
            try {
                $this->db->beginTransaction();
                //校验商品单
                if(!empty($orderId)){
                    //查询订单状态+行锁
                    $orderResult = $this->getOrderStatusByOrderId($orderId);
                    $orderStatus = $orderResult['order_status_id'];
                    //获取订单失效时间
                    $intervalTime = $this->model_checkout_order->checkOrderExpire($orderId);
                    if($orderStatus == FeeOrderStatus::COMPLETE){
                        throw  new PaymentException(PaymentException::ORDER_EXPIRED);
                    }
                    if($orderStatus == FeeOrderStatus::EXPIRED){
                        throw  new PaymentException(PaymentException::ORDER_EXPIRED);
                    }
                    if($intervalTime >= $this->config->get('expire_time')){
                        throw  new PaymentException(PaymentException::ORDER_EXPIRED);
                    }
                }
                //校验费用单
                if(!empty($feeOrderIdArr)) {
                    $feeOrderInfos = $this->feeOrderRepository->findFeeOrderInfo($feeOrderIdArr);
                    $feeOrderStatus = $feeOrderInfos[0]['status'];
                    $date_add = $feeOrderInfos[0]['created_at'];
                    $feeOrderIntervalTime = (time()-strtotime($date_add))/60;
                    if ($feeOrderStatus != FeeOrderStatus::WAIT_PAY) {
                        throw  new PaymentException(PaymentException::ORDER_EXPIRED);
                    }
                    if($feeOrderIntervalTime >= $this->config->get('expire_time')){
                        throw  new PaymentException(PaymentException::ORDER_EXPIRED);
                    }
                }
                //校验支付金额
                $line_of_credit = $this->customer->getLineOfCredit();
                $feeOrderTotal = $this->feeOrderRepository->findFeeOrderTotal($feeOrderIdArr);
                $purchaseOrderTotal = $this->model_checkout_order->getCreditTotal($orderId);
                $creditPayTotal = $purchaseOrderTotal+$feeOrderTotal;
                // #17343 信用额度支付超额使用导致余额为负数 添加信用额度为负数的情况 （因在to_pay页面上的信用额度输入框，忽略了负数，例如：用户实际金额为-100，页面展示为100，传的balance为100）
                if ($line_of_credit < 0 || $creditPayTotal > $line_of_credit) {
                    $feeOrderList = '';
                    foreach ($feeOrderIdArr as $feeOrderId){
                        $feeOrderList .= $feeOrderId.',';
                    }
//                    $feeOrderList = substr($feeOrderList ,0 ,-1);
//                    $json['status'] = 4;
//                    $json['msg'] = "Line of credit not enough!";
//                    $json['redirect'] = str_replace('&amp;', '&', $this->url->link('checkout/confirm/toPay', '&order_id=' . $orderId.'&fee_order_list='.$feeOrderList));
//                    return $json;
                    throw  new PaymentException(PaymentException::LINE_OF_CREDIR_NOT_ENOUGH);
                }
                //支付后续逻辑
                $complete_status = 5;
                //扣减信用额度
                $this->model_checkout_order->payByLineOfCredit($orderId,$feeOrderIdArr,$customerId);
                $this->model_checkout_order->addOrderHistoryByYzcModel($orderId,$feeOrderIdArr,$complete_status);

                $this->db->commit();
                $this->model_checkout_order->addOrderHistoryByYzcModelAfterCommit($orderId);
                $json['status'] = 2;
                $json['redirect'] = $this->url->link('checkout/success&o=' . $orderId.'&f='.implode(',',$feeOrderIdArr), false);
            } catch (Exception $e) {
                //回滚
                $this->db->rollback();
                $msg = "[dealPaymentCallback]line_of_credit失败,customer_id:" . $customerId . ",order_id:" . $orderId . "errorMsg:" . $e->getMessage();
                //记录日志
                Logger::error($msg);
                //异常通知
                $this->model_checkout_order->sendErrorMsg($customerId, $orderId, PayCode::PAY_LINE_OF_CREDIT, $msg);
                $fail_msg = $e->getMessage();
                $json['status'] = $e->getCode() == PaymentException::LINE_OF_CREDIR_NOT_ENOUGH ? -1 : 0;
                $json['msg'] = $fail_msg;
            }
        }
        return $json;
    }

}

