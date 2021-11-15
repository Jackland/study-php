<?php

namespace App\Console\Commands;

use App\Models\Future\FuturesProductLock;
use App\Models\MarketingCoupon\Coupon;
use App\Models\Purchase\PurchaseOrder;
use App\Services\Marketing\MarketingTimeLimitDiscountService;
use DB;
use Illuminate\Console\Command;

class CancelPurchaseOrder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'purchaseOrder:cancel';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '取消超时的采购订单，并且返回预扣库存';


    /**
     * 联动支付的token
     * @var string
     */
    private $umfToken;
    /**
     * 联动支付的服务器url,和商户信息
     * @var string
     */
    private $umfHostUrl = "https://fx.soopay.net/cberest/v1";
    private $clientId = "e1d79940df706110764f9ff42fa887c5453866f0";
    private $clientSecret = "4c46858b58a0273e04061d136db9700bff434db9";
    private $auth_key = "eXpjbUFwaTp5emNtQXBpQDIwMTkwNTE1";
    //服务店铺
    private $serviceStore = [340, 491, 631, 838];


    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->umfToken = "";
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        //获取30分钟未处理的采购订单
        $request = new PurchaseOrder();
        $purchaseOrders = $request->getNoCancelPurchaseOrder();
        //获取包销店铺
        $bxStoreArray = $request->getBxStore();
//        $a= $request->getNoCancelPurchaseOrderLine(114110);
//        $this->lineOfCreditMethod($a[0], $bxStoreArray, $request);
//        die();
        foreach ($purchaseOrders as $purchaseOrder) {
            try {
                //再次判断该采购订单的状态
                $order_status_id = $request->getOrderStatus($purchaseOrder['order_id']);
                if ($order_status_id != 0) {
                    continue;
                } else {
                    \DB::beginTransaction();
                    //获取该订单的明细
                    $purchaseOrderLines = $request->getNoCancelPurchaseOrderLine($purchaseOrder['order_id']);
                    foreach ($purchaseOrderLines as $purchaseOrderLine) {
                        if ($purchaseOrderLine['payment_code'] == 'line_of_credit') {
                            //支付方式为信用额度
                            $this->lineOfCreditMethod($purchaseOrderLine, $bxStoreArray, $request);
                            //cancel采购订单
                            $request->cancelPurchaseOrder($purchaseOrder['order_id']);
                        } else if ($purchaseOrderLine['payment_code'] == 'umf_pay') {
                            //支付方式为联动支付需要查询联动的支付状态
                            $result = $this->umfPayMethod($purchaseOrderLine['order_id'], $bxStoreArray, $request);
                            //交易关闭 回退库存
                            if (!$result) {
                                $this->lineOfCreditMethod($purchaseOrderLine, $bxStoreArray, $request);
                                //cancel采购订单
                                $request->cancelPurchaseOrder($purchaseOrder['order_id']);
                            } else {
                                continue;
                            }
                        } else if ($purchaseOrderLine['payment_code'] == 'cybersource_sop') {
                            //支付方式为cybersource_sop
                            $this->cybersourceMethod($purchaseOrderLine, $bxStoreArray, $request);
                        } else if ($purchaseOrderLine['payment_code'] == 'wechat_pay') {
                            $result = $this->umfPayMethod($purchaseOrderLine['order_id'], $bxStoreArray, $request);
                            //交易关闭 回退库存
                            if (!$result) {
                                $this->lineOfCreditMethod($purchaseOrderLine, $bxStoreArray, $request);
                                //cancel采购订单
                                $request->cancelPurchaseOrder($purchaseOrder['order_id']);
                            }
                        } else if ($purchaseOrderLine['payment_code'] == 'airwallex') {
                            //支付方式为信用额度
                            $this->lineOfCreditMethod($purchaseOrderLine, $bxStoreArray, $request);
                            //cancel采购订单
                            $request->cancelPurchaseOrder($purchaseOrder['order_id']);
                        } else {
                            $result = $this->umfPayMethod($purchaseOrderLine['order_id'], $bxStoreArray, $request);
                            //交易关闭 回退库存
                            if (!$result) {
                                $this->lineOfCreditMethod($purchaseOrderLine, $bxStoreArray, $request);
                                //cancel采购订单
                                $request->cancelPurchaseOrder($purchaseOrder['order_id']);
                            }
                        }
                        // 设置优惠券为未已使用
                    }
                    Coupon::cancelCouponUsed($purchaseOrder['order_id']);
                    MarketingTimeLimitDiscountService::unLockTimeLimitProductQty($purchaseOrder['order_id']);
                    \DB::commit();
                    \Log::info("采购订单id:" . $purchaseOrder['order_id'] . "处理成功!");
                }
            } catch (\Exception $e) {
                \DB::rollback();
                $preMsg = $e->getMessage();
                \Log::error($preMsg);
            }
        }
    }

    public function lineOfCreditMethod($purchaseOrder, $bxStoreArray, $request)
    {
        //获取预出库明细
        $checkMargin = $request->checkMarginProduct($purchaseOrder);

        $checkAdvanceFutures = [];
        $checkRestMargin = $checkRestFutures = [];
        if ($purchaseOrder['type_id'] == 2) {
            $checkRestMargin = $request->checkRestMarginProduct($purchaseOrder);
        } elseif ($purchaseOrder['type_id'] == 3) {
            $checkRestFutures = $request->checkRestFuturesProduct($purchaseOrder);//校验是否是期货尾款
            if (empty($checkRestFutures))
                $checkAdvanceFutures = $request->checkFuturesAdvanceProduct($purchaseOrder);//校验是否是期货头款
        }

        if (!empty($checkMargin)) {
            // 保证金店铺的头款产品
            // 1.更改上架以及combo影响的产品库存
            // 2.oc_order_lock表刪除保证金表数据 履约人表删除数据
            // 3.更改头款商品上架库存产品库存
            $request->rebackMarginSuffixStore($checkMargin['product_id'], $checkMargin['num']);
            $request->deleteMarginProductLock($checkMargin['margin_id']);
            $request->marginStoreReback($purchaseOrder['product_id'], $purchaseOrder['quantity']);
        } elseif (!empty($checkRestMargin) && !in_array($checkRestMargin['seller_id'], $bxStoreArray)) {
            // 保证金店铺的尾款产品
            // 1 .oc_order_lock表更改保证金表数据
            $request->updateMarginProductLock($checkRestMargin['margin_id'], $purchaseOrder['quantity'], $purchaseOrder['order_id']);
            //还到上架库存
            $request->reback_stock_ground($checkRestMargin, $purchaseOrder);
            //退还批次库存
            $preDeliveryLines = $request->getPreDeliveryLines($purchaseOrder['order_product_id']);
            foreach ($preDeliveryLines as $preDeliveryLine) {
                $request->reback_batch($preDeliveryLine);
            }
        } elseif (!empty($checkAdvanceFutures)) {
            $request->updateFuturesAdvanceProductStock($purchaseOrder['product_id']);
            // 释放期货协议锁定的合约数量
            if (empty($checkAdvanceFutures['is_bid'])) {
                $request->unLockAgreementNum($checkAdvanceFutures['agreement_id']);
            }
        } elseif (!empty($checkRestFutures)) {
            //期货尾款
            $productLock = new FuturesProductLock();
            $productLock->TailIn($checkRestFutures['agreement_id'], $purchaseOrder['quantity'], $purchaseOrder['order_id'], 7);
            //退还批次库存
            $preDeliveryLines = $request->getPreDeliveryLines($purchaseOrder['order_product_id']);
            foreach ($preDeliveryLines as $preDeliveryLine) {
                $request->reback_batch($preDeliveryLine);
            }
        } else {
            $preDeliveryLines = $request->getPreDeliveryLines($purchaseOrder['order_product_id']);
            if (count($preDeliveryLines) > 0) {
                //todo 保证金头款处理
                //$checkMargin = $request->checkMarginProduct($purchaseOrder);
                //if (!empty($checkMargin)) {
                //    // 保证金店铺的头款产品
                //    $request->serviceStoreReback($purchaseOrder['product_id'],$purchaseOrder['quantity']);
                //}
                //外部店铺或者包销店铺退库存处理
                if (in_array($purchaseOrder['customer_id'], $bxStoreArray) || $purchaseOrder['accounting_type'] == 2) {
                    //判断是否为combo品
                    if ($purchaseOrder['combo_flag'] == 1) {
                        foreach ($preDeliveryLines as $preDeliveryLine) {
                            $request->rebackStock($purchaseOrder, $preDeliveryLine, true);
                        }
                        //if (!empty($checkMargin)) {
                        //    //保证金原combo退库存
                        //    $request->rebackComboProduct($checkMargin['product_id'], $checkMargin['num']);
                        //}else{
                        //非保证金combo退库存
                        $request->rebackComboProduct($purchaseOrder['product_id'], $purchaseOrder['quantity']);
                        //}
                    } else {
                        //非combo品
                        foreach ($preDeliveryLines as $preDeliveryLine) {
                            $request->rebackStock($purchaseOrder, $preDeliveryLine, true);
                        }
                    }

                } else {
                    //内部店铺的cancel采购订单出库,服务店铺产品
                    //判断是否为combo品
                    if ($purchaseOrder['combo_flag'] == 1) {
                        foreach ($preDeliveryLines as $preDeliveryLine) {
                            $request->rebackStock($purchaseOrder, $preDeliveryLine, false);
                        }
                        //if (!empty($checkMargin)) {
                        //    //保证金原combo退库存
                        //    $request->rebackComboProduct($checkMargin['product_id'], $checkMargin['num']);
                        //}else{
                        //非保证金combo退库存
                        $request->rebackComboProduct($purchaseOrder['product_id'], $purchaseOrder['quantity']);
                        //}
                    } else {
                        foreach ($preDeliveryLines as $preDeliveryLine) {
                            $request->rebackStock($purchaseOrder, $preDeliveryLine, false);
                        }
                    }
                }
            }
            //else if (in_array($purchaseOrder['customer_id'],$this->serviceStore)){
            //    // 保证金店铺的头款产品
            //    $request->serviceStoreReback($purchaseOrder['product_id'],$purchaseOrder['quantity']);
            //}
            else {
                //没有预出库明细
                $msg = "[采购订单超时返还库存错误],采购订单明细：" . $purchaseOrder['order_product_id'] . ",未找到对应预出库记录";
                \Log::warning($msg);
            }
        }

    }


    public function umfPayMethod($order_id, $bxStoreArray, $request)
    {
        try {
            //联动支付查询订单状态接口
            $tradeFlag = false;
            $payInfoResults = $request->getUmfPayId($order_id);
            foreach ($payInfoResults as $payInfoResult) {
                if (isset($payInfoResult['pay_id'])) {
                    $payId = $payInfoResult['pay_id'];
                } else {
                    if (!empty($payInfoResult['pay_req'])) {
                        $pay_req = json_decode($payInfoResult['pay_req'], true);
                        $payId = $pay_req['payment']['id'];
                    } else {
                        $payId = null;
                    }
                }
                if (!isset($payId)) {
                    $errorMsg = "该采购订单：" . $order_id['order_id'] . ":未查询到联动支付PayId";
                    \Log::error($errorMsg);
                    $returnMsg = array(
                        'payment' => array(
                            'state' => 'NO PayId'
                        )
                    );
                    return $returnMsg;
                } else {
                    $requestUrl = "/payments/payment/" . $payId;
                    $umfOrderInfo = $this->sendMsg($requestUrl, 'GET', $order_id);
                    if (isset($umfOrderInfo) && $umfOrderInfo['payment']['state'] != "TRADE_SUCCESS") {
                        $tradeFlag = false;
                    }else{
                        //支付成功
                        $tradeFlag = true;
                        break;
                    }

                }
            }
        } catch (\Exception $e) {
            $preMsg = $e->getMessage();
            $msg = "[UMF采购订单查询订单状态失败],采购订单ID：" . $order_id . ",umfPayMethod方法失败";
            $errorMsg = $msg . $preMsg;
            throw new \Exception($errorMsg);
        }
        return $tradeFlag;
    }

    public function cybersourceMethod($purchaseOrder, $bxStoreArray, $request)
    {
        $payInfoResults = $request->getUmfPayId($purchaseOrder['order_id']);
        $tradeFlag = false;
        foreach ($payInfoResults as $payInfoResult) {
            if(isset($payInfoResult) && $payInfoResult['status'] == '201'){
                $tradeFlag = true;
                break;
            }
        }
        if(!$tradeFlag) {
            $this->lineOfCreditMethod($purchaseOrder, $bxStoreArray, $request);
            //cancel采购订单
            $request->cancelPurchaseOrder($purchaseOrder['order_id']);
        }
    }

    public function sendMsg($url, $reqMethod, $order_id)
    {
        if (strtoupper($reqMethod) != "GET") {
            $msg = "[UMF采购订单查询订单状态失败],采购订单ID：" . $order_id . ",查询方式不为GET请求";
            throw new \Exception($msg);
        }
        try {
            //获取请求的token
            if (!empty($this->umfToken)) {
                $testToken = $this->send($url, $reqMethod);
                if (isset($testToken['meta']['ret_code']) && $testToken['meta']['ret_code'] = '00280703') {
                    $this->umfToken = "";
                    $this->refreshToken();
                    $resMessage = $this->send($url, $reqMethod);
                }
            } else {
                $this->refreshToken();
                $resMessage = $this->send($url, $reqMethod);
            }
            return $resMessage;
        } catch (\Exception $e) {
            $msg = "[UMF采购订单查询订单状态失败],采购订单ID：" . $order_id . ",sendMsg方法失败";
            throw new \Exception($msg . "\t" . $e->getMessage());
        }
    }

    public function refreshToken()
    {
        try {
            if (empty($this->umfToken)) {
                $hostUrl = $this->umfHostUrl;
                $reqUrl = $hostUrl . "/oauth/authorize";
                $reqBodyArray = array(
                    "grant_type" => "client_credentials",
                    "client_secret" => $this->clientSecret,
                    "client_id" => $this->clientId
                );
                $body = json_encode($reqBodyArray);
                $header = array('Content-Type: application/json');
                $responseResult = $this->curlRequest($reqUrl, "POST", $header, $body);
                $this->umfToken = $responseResult['access_token'];
                if (empty($this->umfToken)) {
                    return false;
                } else {
                    return true;
                }
            }
        } catch (\Exception $e) {
            $msg = "[UMF采购订单查询订单状态失败],refreshToken方法失败";
            throw new \Exception($msg . "\t" . $e->getMessage());
        }
    }

    public function curlRequest($url, $reqMethod, $header, $body = null)
    {
        // 初始化
        $curl = curl_init();
        // 设置抓取的url
        curl_setopt($curl, CURLOPT_URL, $url);
        // 设置头文件的信息作为数据流输出
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        if (strtoupper($reqMethod) == 'POST') {
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        }
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 60);
        // 设置获取的信息输出。
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        //取消ssl证书验证
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        // 执行命令
        $pay_result = curl_exec($curl);
        var_dump(curl_error($curl));
        // 关闭URL请求
        curl_close($curl);
        // 将结果转为数组
        $pay_result = json_decode($pay_result, true);
        return $pay_result;
    }

    public function send($reqUrl, $reqMethod)
    {
        try {
            $header = array('Content-Type: application/json', 'Authorization:Bearer' . $this->umfToken, 'Accept-Language:ZH');
            $result = $this->curlRequest($this->umfHostUrl . $reqUrl, $reqMethod, $header);
            return $result;
        } catch (\Exception $e) {
            $msg = "[UMF采购订单查询订单状态失败],send方法失败";
            throw new \Exception($msg . "\t" . $e->getMessage());
        }
    }
}
