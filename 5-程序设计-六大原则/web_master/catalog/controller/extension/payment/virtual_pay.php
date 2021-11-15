<?php

use App\Enums\FeeOrder\FeeOrderFeeType;
use App\Enums\Pay\PayCode;
use App\Enums\Pay\VirtualPayType;
use App\Exception\SalesOrder\AssociatedException;
use App\Enums\FeeOrder\FeeOrderStatus;
use App\Logging\Logger;
use App\Repositories\FeeOrder\FeeOrderRepository;

/**
 * Created by IntelliJ IDEA.
 * User: or
 * Date: 2020/6/1
 * Time: 10:48
 */
/**
 * @property ModelCheckoutOrder $model_checkout_order
 * @property ModelCheckoutPay $model_checkout_pay
 * @property ModelAccountBalanceVirtualPayRecord $model_account_balance_virtual_pay_record
 * */
class ControllerExtensionPaymentVirtualPay extends Controller
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

    public function confirm($data)
    {
        $this->load->model('checkout/order');
        $this->load->model('checkout/pay');
        $this->load->model('account/balance/virtual_pay_record');
        $json = [];
        $orderId = $data['order_id'];
        $feeOrderIdArr = $data['fee_order_id'];
        if (!empty($orderId) || !empty($feeOrderIdArr)) {
                // 购买订单成功
                $customer_id = $this->customer->getId();
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
                            $json['status'] = 4;
                            $json['msg'] = "The order has been paid!";
                            return $json;
                        }
                        if($orderStatus == FeeOrderStatus::EXPIRED){
                            $json['status'] = 4;
                            $json['msg'] = "The order has been expired!";
                            return $json;
                        }
                        if($intervalTime >= $this->config->get('expire_time')){
                            $json['status'] = 4;
                            $json['msg'] = "The order has been expired!";
                            return $json;
                        }
                    }
                    $feeOrderInfos = [];
                    //校验费用单
                    if(!empty($feeOrderIdArr)) {
                        $feeOrderInfos = $this->feeOrderRepository->findFeeOrderInfo($feeOrderIdArr);
                        $feeOrderStatus = $feeOrderInfos[0]['status'];
                        $date_add = $feeOrderInfos[0]['created_at'];
                        $feeOrderIntervalTime = (time()-strtotime($date_add))/60;
                        if($feeOrderStatus == FeeOrderStatus::COMPLETE){
                            $json['status'] = 4;
                            $json['msg'] = "The order has been paid!";
                            return $json;
                        }
                        if($feeOrderStatus == FeeOrderStatus::EXPIRED){
                            $json['status'] = 4;
                            $json['msg'] = "The order has been expired!";
                            return $json;
                        }
                        if($feeOrderStatus == FeeOrderStatus::REFUND){
                            $json['status'] = 4;
                            $json['msg'] = "The order has been refunded!";
                            return $json;
                        }
                        if($feeOrderIntervalTime >= $this->config->get('expire_time')){
                            $json['status'] = 4;
                            $json['msg'] = "The order has been expired!";
                            return $json;
                        }
                    }
                    //校验支付金额
                    $line_of_credit = $this->customer->getLineOfCredit();
                    $feeOrderTotal = $this->feeOrderRepository->findFeeOrderTotal($feeOrderIdArr);
                    $purchaseOrderTotal = $this->model_checkout_order->getCreditTotal($orderId);
                    $creditPayTotal = $purchaseOrderTotal+$feeOrderTotal;

                    //支付后续逻辑
                    $complete_status = FeeOrderStatus::COMPLETE;
                    if(!empty($orderId)){
                        $this->model_account_balance_virtual_pay_record->insertData($customer_id,$orderId,$purchaseOrderTotal, VirtualPayType::PURCHASE_ORDER_PAY);
                    }
                    foreach ($feeOrderInfos as $feeOrder){
                        $feeOrderId = $feeOrder['id'];
                        $virtualPayType = 0;
                        if ($feeOrder['fee_type'] === FeeOrderFeeType::STORAGE) {
                            $virtualPayType = VirtualPayType::STORAGE_FEE;
                        } elseif ($feeOrder['fee_type'] === FeeOrderFeeType::SAFEGUARD) {
                            $virtualPayType = VirtualPayType::SAFEGUARD_PAY;
                        }
                        $this->model_account_balance_virtual_pay_record->insertData($customer_id,$feeOrderId,$feeOrder['fee_total'],$virtualPayType);
                    }
                    $this->model_checkout_order->addOrderHistoryByYzcModel($orderId,$feeOrderIdArr,$complete_status);
                    $this->db->commit();
                    $this->model_checkout_order->addOrderHistoryByYzcModelAfterCommit($orderId);
                    $json['status'] = 2;
                    $json['redirect'] = $this->url->link('checkout/success&o='.$orderId.'&f='.implode(',',$feeOrderIdArr),false);
				} catch (AssociatedException $e) {
                    $this->db->rollback();
                    $json['status'] = "0";
                    $json['order_status'] = 1;
                    $json['msg'] = $e->getMessage();
                    $this->log->write($e->getMessage());
                    if ($e->getSalesOrder()) {
                        $json['redirect'] = $this->url->to(['account/sales_order/sales_order_management', 'filter_orderId' => $e->getSalesOrder()]);
                    } else {
                        $json['redirect'] = $this->url->to(['account/sales_order/sales_order_management']);
                    }
                    return $json;
                } catch (Exception $e) {
                    //回滚
                    $this->db->rollback();
                    $msg = "[dealPaymentCallback]
                    virtual_pay失败
                    customer_id:" . $customer_id . ",order_id:" . $orderId . "
                    errorMsg:" . $e->getMessage();

                    //记录日志
                    Logger::error($msg);
                    //异常通知
                    $this->model_checkout_order->sendErrorMsg($customer_id, $orderId, PayCode::PAY_LINE_OF_CREDIT, $msg);

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
                $json['status'] = 2;
                $this->session->set('error','Payment failed, we will deal with it as soon as possible. If you have any questions, please contact us.');
                Logger::error("订单支付order_id不存在");
                $json['redirect'] = $this->url->link('checkout/cart');
            }
        return $json;
    }

}
