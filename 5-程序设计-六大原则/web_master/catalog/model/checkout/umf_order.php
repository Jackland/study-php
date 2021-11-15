<?php

use App\Enums\Product\ProductType;
use App\Logging\Logger;
use App\Models\Product\Product;
use App\Repositories\FeeOrder\FeeOrderRepository;
use Carbon\Carbon;

/**
 * Class ModelCheckoutUmforder
 * @property ModelCheckoutOrder $model_checkout_order
 * @property ModelCheckoutUmfOrder $model_checkout_umf_order
 */
class ModelCheckoutUmforder extends Model
{
    private $result = array();
    const ORDER_TOTAL_ZERO = 100;
    const CREATE_ERROR = 101;

    public function createPayment($data)
    {
        $post_param = $this->createPostData($data);
        if(isset($post_param['status']) && $post_param['status'] == self::ORDER_TOTAL_ZERO){
            //订单金额为0 直接支付成功
            Logger::app('订单金额为0  umf直接支付成功  [customer]' . $this->customer->getId() . ',[order_id]' . json_encode($data));
            $this->load->model('checkout/order');
            $result = $this->model_checkout_order->processingUmfOrderCompleted($data);
            if ($result['success']) {
                return ['status' => 5];
            } else {
                return ['status' => 0, 'msg' => $result['msg']];
            }
        }
        if(isset($post_param['status']) && $post_param['status'] == self::CREATE_ERROR){
            Logger::error("[umf_pay]创建订单失败 customer_id:" . $this->customer->getId()
                . ',retMsg:' . json_encode($post_param));
            $this->result['msg'] = 'Sorry, the system is busy now, please try again later.';
            return $this->result;
        }
        $returnData = $this->sendMsg(URL_YZCM . '/api/umpay/payment', $post_param);
        if (!$returnData) {
            $errorMsg = '[umf_pay]创建订单失败:接口无响应.';
            $this->model_checkout_umf_order->sendErrorMsg('umf_pay', $this->customer->getId(), $errorMsg);
            Logger::error($errorMsg, 'error');
            $this->result['msg'] = 'Sorry, the system is busy now, please try again later.';
            return $this->result;
        }
        $retMsg = json_decode($returnData['retMsg'], true);
        if (isset($retMsg['url'])) {
            //成功
            $this->result['pay_url'] = $retMsg['url'];
            $this->result['status'] = 1;
            $this->result['msg'] = 'Create order success.';
            return $this->result;
        } else {
            Logger::error(['[umf_pay]创建订单失败', 'data' => $data, 'post' => $post_param, 'returnData' => $returnData], 'error');
            $this->result['msg'] = 'Sorry, the system is busy now, please try again later.';
            return $this->result;
        }
    }

    /**
     * @param $query_url
     * @param $post_data
     * @return array
     */
    public function sendMsg($query_url, $post_data = [])
    {
        // 初始化
        $curl = curl_init();
        // 设置抓取的url
        curl_setopt($curl, CURLOPT_URL, $query_url);
        // 设置头文件的信息作为数据流输出
        curl_setopt($curl, CURLOPT_HEADER, 0);
        // 设置获取的信息输出。
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        // 设置post方式提交
        curl_setopt($curl, CURLOPT_POST, 1);
        // 取消ssl证书验证
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);

        // 设置post数据
        $post_data = json_encode($post_data);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json',
            'Content-Length: ' . strlen($post_data),
            'Authorization: Basic ' . AUTH_KEY));
        curl_setopt($curl, CURLOPT_POSTFIELDS, $post_data);
        // 执行命令
        $data = curl_exec($curl);
        // 关闭URL请求
        curl_close($curl);
        // 将结果转为数组
        $data = json_decode($data, true);
        return $data;
    }


    public function checkCurrency($currency_row)
    {
        $support_currency = $this->config->get('payment_umf_pay_currency');
        if (empty($support_currency)) {
            $support_currency = 'USD';
        }
        $support_currency = explode(',', $support_currency);
        if (!isset($currency_row['code']) || !in_array($currency_row['code'], $support_currency)) {

            $this->result['msg'] = 'UMF Pay currently supports only ';
            $currency_query = $this->db->query("select title from oc_currency where code in ('" . implode("','", $support_currency) . "')")->rows;
            if (!empty($currency_query)) {
                $titles = array();
                foreach ($currency_query as $it) {
                    $titles [] = $it['title'];
                }
                $this->result['msg'] .= implode(',', $titles);
            } else {
                $this->result['msg'] .= 'USD';
            }
            return false;
        }
        return true;
    }

    /**
     * 创建订单失败后发送消息
     */
    public function sendErrorMsg($pay_method, $customer_id, $error_msg)
    {
        $mail_subject = '【重要】联动支付创建订单失败';
        $mail_body = "<br><h3>$mail_subject</h3></a><hr>
<table   border='0' cellspacing='0' cellpadding='0' >
<tr><th align='left'>环境:</th><td>" . HTTPS_SERVER . "</td></tr>
<tr><th align='left'>用户ID:</th><td>$customer_id</td></tr>
<tr><th align='left'>pay_method:</th><td>$pay_method</td></tr>
</table><br>error_msg:   $error_msg";
        //payment_fail_send_mail:  true或null 时发邮件
        //     只有为false时  才不发邮件
        $mail_enable = configDB('payment_fail_mail_enable');
        $mail_to = configDB('payment_fail_mail_to');
        $mail_cc = configDB('payment_fail_mail_cc');
        if ((!empty($mail_enable) || $mail_enable) && (!empty($mail_to) || $mail_to)) {
            $mail = new Phpmail();
            $mail->subject = $mail_subject;
            $mail->to = $mail_to;
            if (!empty($mail_cc)) {
                $mail->cc = explode(',', $mail_cc);
            }
            $mail->body = $mail_body;
            $mail->send(true);
        }
    }

    public function createPostData1($order_id, $order_info, $order_products,$payment_method,$payData=null,$feeOrderInfos=null)
    {
        //查看订单的议价数据
        $quoteInfos = $this->model_checkout_order->getOrderQuoteInfo($order_id);
        //欧洲运费产品
        $sellerProductId = json_decode($this->config->get('europe_freight_product_id'),true);
        //前台通知
        //回调函数需进入session   k:sessionId   o:yzcOrderId
        $ret_url = HTTPS_SERVER . "?route=checkout/success/toSuccessPage&k={$this->session->getId()}&o=$order_id";
        $customer_id = $order_info['customer_id'] . '@#@' . $order_id;
        //根据buyer查询货币单位
        $currency_sql = "SELECT cny.value, cny.code FROM `oc_currency` cny  INNER JOIN  oc_country t1 ON cny.`currency_id` = t1.`currency_id` INNER JOIN  `oc_customer` t2 ON t1.`country_id`=t2.`country_id` WHERE t2.`customer_id` =  " . $order_info['customer_id'];
        $currency_row = $this->db->query($currency_sql)->row;
        if (!$this->checkCurrency($currency_row)) {
            return $this->result;
        }
        if ($currency_row['code'] == 'USD' && $payment_method !='wechat_order') {
            $currency_code = 'USD';
            $currency_value = 1;
        }else if($payment_method == 'wechat_order' &&  $currency_row['code'] == 'USD'){
            $currency = $this->db->query('SELECT cny.value, cny.code FROM `oc_currency` cny where currency_id=5')->row;
            $currency_code = 'CNY';
            $currency_value = round((double)$currency['value'], 4);
        } else {
            //** 除美元外都换算成CNY  currency表中存的汇率也是CNY的汇率
            $currency_code = 'CNY';
            $currency_value = round((double)$currency_row['value'], 4);
        }
        $post_param = array('payment' => null, 'session_id' => $this->session->getId(),
            'callback' => HTTP_SERVER,
            'rate_yzc' => $currency_value, 'currency_yzc' => $currency_row['code'], 'total_yzc' => $order_info['total']);


        $ip = $order_info['ip'];
        $comment = 'GIGACloud Logistics #' . $order_id;
        $trans_code_goods = '01122030';
        //将手续费放到服务费中
        $trans_code_service = '07222032';
        if ($ip == '::1') {
            $ip = '127.0.0.1';
        }

        //订单明细
        $order_total_query = $this->db->query("SELECT * FROM oc_order_total WHERE order_id = $order_id")->rows;
        //订单最终金额
        $order_total = 0;
        //订单使用余额
        $order_balance = 0;
        //订单手续费
        $poundage = 0;
        //订单货值金额(货值+服务费+运费)
        $product_total = 0;
        foreach ($order_total_query as $row) {
            if ($row['code'] == 'total') {
                $order_total += $row['value'];
            }else if($row['code'] == 'balance'){
                $order_balance += $row['value'];
            }else if($row['code'] == 'poundage'){
                $poundage += $row['value'];
            }else{
                $product_total += $row['value'];
            }
        }
        //计算补运费产品的中金额
        $freightTotal = $this->freightProductTotal($order_id);
        //包含货值和运费
        $order_total_currency = round((double)$order_total * $currency_value, 2);
        //货值total
        $good_total_currency = round((double)($order_total-$poundage-$freightTotal) * $currency_value, 2);
        //手续费total
        $service_fee_total = round($order_total_currency-$good_total_currency,2);
        //货物费用
        $goods_fee = 0;
        $sub_orders = array();
        //将最后的价格拆分到对应明细上
        //订单明细的数量
        $lineCount = count($order_products);
        foreach ($order_products as $index => $orderProduct) {
            if (($index + 1) == $lineCount) {
                //将差额补充到最后一次
                $item_fee_total = round($good_total_currency - $goods_fee, 2);
                $goods_fee += $item_fee_total;
            } else {
                $productPrice = isset($quoteInfos[$orderProduct['product_id']])?$quoteInfos[$orderProduct['product_id']]['price']:($orderProduct['price']);
                $lineTotal = ($productPrice+$orderProduct['freight_per'] + $orderProduct['package_fee']) * $orderProduct['quantity'];
                $item_fee_total = round(($lineTotal / $product_total) * $good_total_currency, 2);
                $goods_fee += $item_fee_total;
            }
            if ($item_fee_total > 0) {
                $item = [];
                $item[] = array(
                    "type" => "OTHER",
                    'quantity' => $orderProduct['quantity'],
                    "amount" => array("total" => $item_fee_total, "currency" => $currency_code),
                    'name' => $orderProduct['product_id'] . "@#@" . $orderProduct['name'],
                );
                $sub_orders[] = array(
                    "is_customs" => "false",
                    "trans_code" => $trans_code_goods,
                    "amount" => array(
                        "total" => round($item_fee_total, 2),
                        "currency" => $currency_code
                    ),
                    "items" => $item,
                );
            }
        }
        //运费子订单,手续费添加到运费中
        if ($service_fee_total>0) {
            $sub_orders[] = array(
                "trans_code" => $trans_code_service,
                "amount" => array(
                    "total" => round($service_fee_total, 2),
                    "currency" => $currency_code
                ),
            );
        }

        if('umf_order' == $payment_method) {
            //回调
            $notify_url = HTTPS_SERVER . '?route=extension/payment/umf_pay/callbackByUmpay';
            $payment = array(
                "payer" => array(
                    "payment_method" => "NOT_APPLICABLE",
                    "payer_info" => (object)array(),
                    "interface_type" => "SERVER_TO_WEB",
                    "external_customer_id" => $customer_id,
                    "business_type" => "B2C",
                ),
                "order" => array(
                    "mer_date" => date('Ymd', time()),
                    "amount" => array(
                        "total" => round($order_total_currency, 2),
                        "currency" => $currency_code
                    ),
                    "order_summary" => $comment,
                    //支付链接过期时间
                    "expire_time" => $this->config->get('expire_time'),
                    "user_ip" => $ip,
                    "sub_orders" => $sub_orders,
                ),
                "notify_url" => $notify_url,
                "ret_url" => $ret_url,
                "risk_info" => array(
                    // 02消费  01充值
                    "trans_type" => "02"
                )
            );
            $post_param['payment'] = $payment;
            $post_param['comment'] = isset($order_info['comment']) ? $order_info['comment'] : '';
        }
        if('wechat_order' == $payment_method) {
            $notify_url = HTTPS_SERVER . '?route=extension/payment/wechat_pay/callbackByUmpay';
            $payment = array(
                "payer" => array(
                    "payment_method" => "WECHAT_SCAN",
                    "interface_type" => "SERVER_TO_SERVER",
                    "business_type" => "B2C",
                    "payer_info" => (object)array(
                        'name' => $payData['wechatName'],
                        'phone' => $payData['wechatPhone'],
                        'qr_code_scan' => (object)[
                            'citizen_id_type' => "IDENTITY_CARD",
                            'citizen_id_number' => $payData['wechatIdCard']
                        ],
                    ),
                    "external_customer_id" => $customer_id,
                ),
                "order" => array(
                    "mer_date" => date('Ymd', time()),
                    "amount" => array(
                        "total" => round($order_total_currency, 2),
                        "currency" => $currency_code
                    ),
                    "order_summary" => $comment,
                    //支付链接过期时间
                    "expire_time" => $this->config->get('expire_time'),
                    "user_ip" => $ip,
                    "sub_orders" => $sub_orders,
                ),
                "notify_url" => $notify_url,
                "risk_info" => (object)array(
                    // 02消费  01充值
                    "trans_type" => "02"
                )
            );
            $post_param['payment'] = $payment;
            $post_param['payment_method'] = 'wechat_pay';
            $post_param['comment'] = isset($order_info['comment'])?$order_info['comment']:'';
            $post_param['currency_code'] = $currency_row['code'];
            $post_param['rate_yzc'] = $currency_value;
            $post_param['total'] = $order_total_currency;
        }
        return $post_param;
    }

    /**
     * @Author xxl
     * @Description 创建第三方支付数据
     * @Date 19:56 2020/9/29
     * @param $dataArr  $data = ['order_id' => $order_id,'fee_order_id' => $fee_order_id,'balance' => $balance,'payment_code' => $payment_code,'comment' => $comment,'pay_data' => $payData];
     * @return array
     **/
    public function createPostData($dataArr)
    {
        //商品单订单号
        $orderId = isset($dataArr['order_id']) ? $dataArr['order_id'] : 0;

        //费用单订单号
        $feeOrderId = $dataArr['fee_order_id'] == 0 ? []:$dataArr['fee_order_id'];
        //支付方式
        $paymentMethod = $dataArr['payment_code'];
        if($orderId == 0 && empty($feeOrderId)){
            return false;
        }
        $this->load->model('checkout/order');
        //查看订单的议价数据
        $quoteInfos = $this->model_checkout_order->getOrderQuoteInfo($orderId);
        $orderInfo = $this->model_checkout_order->getOrder($orderId);
        $orderProducts = $this->model_checkout_order->getOrderProductsExcludeFreightProductId($orderId);
        $feeOrderInfos = app(FeeOrderRepository::class)->findFeeOrderInfo($feeOrderId);
        if(empty($orderInfo) && empty($orderProducts) && empty($feeOrderInfos)){
            return false;
        }
        // #38226 重新获取订单信息包含补运费的产品
        $orderProducts = $this->model_checkout_order->getOrderProducts($orderId);

        //前台通知
        //回调函数需进入session   k:sessionId   o:yzcOrderId
        $retUrl = HTTPS_SERVER . "?route=checkout/success/toSuccessPage";
        $feeOrderIdStr = implode(',',$feeOrderId);
        $customerId = $this->customer->getId() . '@#@' . $orderId ."@#@".$feeOrderIdStr;
        //根据buyer查询货币单位
        $currencySql = "SELECT cny.value, cny.code FROM `oc_currency` cny  INNER JOIN  oc_country t1 ON cny.`currency_id` = t1.`currency_id` INNER JOIN  `oc_customer` t2 ON t1.`country_id`=t2.`country_id` WHERE t2.`customer_id` =  " . $this->customer->getId();
        $currencyRow = $this->db->query($currencySql)->row;
        if (!$this->checkCurrency($currencyRow)) {
            return ['status'=>self::CREATE_ERROR];
        }
        if ($currencyRow['code'] == 'USD' && $paymentMethod !='wechat_pay') {
            $currencyCode = 'USD';
            $currencyValue = 1;
        } else if($paymentMethod == 'wechat_pay' &&  $currencyRow['code'] == 'USD'){
            $currency = $this->orm->table('oc_currency')
                ->where('currency_id','=',5)
                ->value('value');
            $currencyCode = 'CNY';
            $currencyValue = round((double)$currency, 4);
        }else {
            //** 除美元外都换算成CNY  currency表中存的汇率也是CNY的汇率
            $currencyCode = 'CNY';
            $currencyValue = round((double)$currencyRow['value'], 4);
        }


        $ip = $orderInfo['ip'];
        $comment = 'GIGACloud Logistics #' . $orderInfo['comment'];
        $transCodeGoods = '01122030';
        //将手续费放到服务费中
        $transCodeService = '07222032';
        if ($ip == '::1') {
            $ip = '127.0.0.1';
        }
        //商品单订单最终金额
        $purchaseOrderTotal = 0;
        //商品订单使用余额
        $purchaseOrderBalance = 0;
        //商品订单手续费
        $purchasePoundage = 0;
        //订单货值金额(货值+服务费+运费)
        $productTotal = 0;
        if(!empty($orderId)){
            //商品单订单金额明细
            $purchaseOrderTotalQuery = $this->orm->table('oc_order_total')
                ->select('code','value')
                ->where('order_id','=',$orderId)
                ->get();
            $purchaseOrderTotalQuery = obj2array($purchaseOrderTotalQuery);
            foreach ($purchaseOrderTotalQuery as $row) {
                if ($row['code'] == 'total') {
                    $purchaseOrderTotal += $row['value'];
                }else if($row['code'] == 'balance'){
                    $purchaseOrderBalance += $row['value'];
                }else if($row['code'] == 'poundage'){
                    $purchasePoundage += $row['value'];
                }else{
                    $productTotal += $row['value'];
                }
            }
        }

        //费用单订单金额汇总
        $feeOrderTotal = 0;
        $feeOrderBalance = 0;

        $feeOrderPoundage = 0;

        foreach ($feeOrderInfos as $feeOrderInfo){
            $feeOrderTotal += $feeOrderInfo['fee_total'];
            $feeOrderBalance += $feeOrderInfo['balance'];
            $feeOrderPoundage += $feeOrderInfo['poundage'];
        }
        //商品单和费用单总值
        $orderTotalYzc = round($purchaseOrderTotal+$feeOrderTotal+$feeOrderPoundage-$feeOrderBalance, 2);
        if($orderTotalYzc == 0 ){
            return ['status'=>self::ORDER_TOTAL_ZERO];
        }
        //订单明细的数量
        $lineCount = count($orderProducts);
        $orderTotalCurrency = round((double)($purchaseOrderTotal+$feeOrderTotal+$feeOrderPoundage-$feeOrderBalance) * $currencyValue, 2);
        //货值total
        $goodTotalCurrency = $lineCount > 0 ? round((double)($purchaseOrderTotal - $purchasePoundage) * $currencyValue, 2) : 0;
        //手续费total(商品单和费用单的总金额-商品单的货值total)
        $serviceFeeTotal = round($orderTotalCurrency-$goodTotalCurrency,2);

        $postParam = array('payment' => null, 'session_id' => $this->session->getId(),
            'callback' => HTTP_SERVER,
            'rate_yzc' => $currencyValue, 'currency_yzc' => $currencyRow['code'], 'total_yzc' => $orderTotalYzc);
        //货物费用
        $goodsFee = 0;
        $subOrders = [];

        // 38226 欧洲和日本补运费商品的采购单，传给联动优势的时候不要传商品属性，应该传运输属性
        $productIds = array_column($orderProducts, 'product_id');
        $compensationFreightProductIds = Product::query()
            ->whereIn('product_id', $productIds)
            ->where('product_type', ProductType::COMPENSATION_FREIGHT)
            ->pluck('product_id')
            ->toArray();

        //将最后的价格拆分到对应明细上
        foreach ($orderProducts as $index => $orderProduct) {
            // 剩余货值总和
            $left = round($goodTotalCurrency - $goodsFee,2);
            if ($left <= 0) {
                continue;
            }
            // 计算单个产品的货值
            $productPrice = isset($quoteInfos[$orderProduct['product_id']])?$quoteInfos[$orderProduct['product_id']]['price']:($orderProduct['price']);
            $lineTotal = ($productPrice+$orderProduct['freight_per'] + $orderProduct['package_fee']) * $orderProduct['quantity'];
            $itemFeeTotal = round(($lineTotal / $productTotal) * $goodTotalCurrency, 2);
            if ($itemFeeTotal > $left) {
                // 若单个产品的货值大于剩余值，单个产品取剩余货值
                $itemFeeTotal = $left;
            }
            if(($index+1) == $lineCount) {
                // 最后一个产品，取全部的剩余货值
                $itemFeeTotal = $left;
            }
            $goodsFee += $itemFeeTotal;
            if ($itemFeeTotal > 0) {
                if (!in_array($orderProduct['product_id'], $compensationFreightProductIds)) {
                    $item = [];
                    $item[] = array(
                        "type" => "OTHER",
                        'quantity' => $orderProduct['quantity'],
                        "amount" => array("total" => $itemFeeTotal, "currency" => $currencyCode),
                        'name' => $orderProduct['product_id']."@#@".$orderProduct['name'],
                    );
                    $subOrders[] = array(
                        "is_customs" => "false",
                        "trans_code" => $transCodeGoods,
                        "amount" => array(
                            "total" => round($itemFeeTotal, 2),
                            "currency" => $currencyCode
                        ),
                        "items" => $item,
                    );
                } else {
                    // 补运费加到运费中
                    $serviceFeeTotal += $itemFeeTotal;
                }
            }
        }
        //运费子订单,手续费添加到运费中
        if ($serviceFeeTotal>0) {
            $subOrders[] = array(
                "trans_code" => $transCodeService,
                "amount" => array(
                    "total" => round($serviceFeeTotal, 2),
                    "currency" => $currencyCode
                ),
            );
        }
        //region 计算传给第三方支付的有效期
        $expireTime = $this->config->get('expire_time');
        if ($orderInfo || !empty($feeOrderInfos)) {
            if ($orderInfo) {
                // 优先使用采购单的时间
                $orderCreatedTime = Carbon::parse($orderInfo['date_added']);
            } else {
                // 其次使用费用单的时间
                $orderCreatedTime = Carbon::parse($feeOrderInfos[0]['created_at']);
            }
            // 计算过期时间
            $orderCreatedTime = $orderCreatedTime->addMinute($expireTime);
            // 计算当前时间与过期时间的差值
            $expireTime = Carbon::now()->diffInMinutes($orderCreatedTime, false);
        }
        // 如果过期时间小于等于0 返回错误
        if ($expireTime <= 0) {
            return ['status' => self::CREATE_ERROR];
        }
        //endregion

        if('umf_pay' == $paymentMethod) {
            //回调
            $notifyUrl = HTTPS_SERVER . '?route=extension/payment/umf_pay/callbackByUmpay';
            $payment = array(
                "payer" => array(
                    "payment_method" => "NOT_APPLICABLE",
                    "payer_info" => (object)array(),
                    "interface_type" => "SERVER_TO_WEB",
                    "external_customer_id" => $customerId,
                    "business_type" => "B2C",
                ),
                "order" => array(
                    "mer_date" => date('Ymd', time()),
                    "amount" => array(
                        "total" => round($orderTotalCurrency, 2),
                        "currency" => $currencyCode
                    ),
                    "order_summary" => $comment,
                    //支付链接过期时间
                    "expire_time" => $expireTime,
                    "user_ip" => $ip,
                    "sub_orders" => $subOrders,
                ),
                "notify_url" => $notifyUrl,
                "ret_url" => $retUrl,
                "risk_info" => array(
                    // 02消费  01充值
                    "trans_type" => "02"
                )
            );
            $postParam['payment'] = $payment;
            $postParam['comment'] = isset($orderInfo['comment']) ? $orderInfo['comment'] : '';
            $postParam['balance'] = isset($dataArr['balance']) ? $dataArr['balance'] : 0;
        }
        if('wechat_pay' == $paymentMethod) {
            $notifyUrl = HTTPS_SERVER . '?route=extension/payment/wechat_pay/callbackByUmpay';
            $payData = $dataArr['pay_data'];
            $payment = array(
                "payer" => array(
                    "payment_method" => "WECHAT_SCAN",
                    "interface_type" => "SERVER_TO_SERVER",
                    "business_type" => "B2C",
                    "payer_info" => (object)array(
                        'name' => $payData['wechatName'],
                        'phone' => $payData['wechatPhone'],
                        'qr_code_scan' => (object)[
                            'citizen_id_type' => "IDENTITY_CARD",
                            'citizen_id_number' => $payData['wechatIdCard']
                        ],
                    ),
                    "external_customer_id" => $customerId,
                ),
                "order" => array(
                    "mer_date" => date('Ymd', time()),
                    "amount" => array(
                        "total" => round($orderTotalCurrency, 2),
                        "currency" => $currencyCode
                    ),
                    "order_summary" => $comment,
                    //支付链接过期时间
                    "expire_time" => $expireTime,
                    "user_ip" => $ip,
                    "sub_orders" => $subOrders,
                ),
                "notify_url" => $notifyUrl,
                "risk_info" => (object)array(
                    // 02消费  01充值
                    "trans_type" => "02"
                )
            );
            $postParam['payment'] = $payment;
            $postParam['payment_method'] = 'wechat_pay';
            $postParam['comment'] = isset($orderInfo['comment'])?$orderInfo['comment']:'';
            $postParam['currency_code'] = $currencyRow['code'];
            $postParam['rate_yzc'] = $currencyValue;
            $postParam['total'] = $orderTotalCurrency;
            $postParam['balance'] = isset($dataArr['balance']) ? $dataArr['balance'] : 0;
        }
        Logger::app("用户发起支付[{$paymentMethod}]请求,订单ID:{$orderId},费用单ID:" . implode(',', $feeOrderId) . ',请求参数:' . json_encode($postParam));
        return $postParam;
    }

    private function freightProductTotal($order_id)
    {
        $freightProductId = json_decode($this->config->get('europe_freight_product_id'),true);
        $freightTotal = $this->orm->table('oc_order_product as oop')
            ->where('oop.order_id','=',$order_id)
            ->whereIn('oop.product_id',$freightProductId)
            ->sum('oop.total');
        return $freightTotal;
    }
}
