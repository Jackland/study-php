<?php

use App\Exception\PaymentException;
use App\Logging\Logger;
use App\Repositories\FeeOrder\FeeOrderRepository;

/**
 * Class ModelCheckoutWechatorder
 * @property ModelCheckoutOrder $model_checkout_order
 * @property ModelCheckoutUmfOrder $model_checkout_umf_order
 */
class ModelCheckoutWechatorder extends Model
{
    const TIME_OUT = 303;
    public function createPayment1($order_id,$fee_order_id,$payData)
    {
        $this->load->model('checkout/order');
        $this->load->model('checkout/umf_order');
        // 获取订单
        $order_info = $this->model_checkout_order->getOrder($order_id);
        $order_products = $this->model_checkout_order->getOrderProductsExcludeFreightProductId($order_id);
        $feeOrderInfos = app(FeeOrderRepository::class)->findFeeOrderInfo(json_encode($fee_order_id,true));
        if ((empty($order_info) || empty($order_products)) && count($feeOrderInfos)==0) {
            return Json::fail('The order information is wrong, please refresh the page or add the shopping cart again!');
        }
        $post_param = $this->model_checkout_umf_order->createPostData($order_id, $order_info, $order_products,'wechat_order',$payData,$feeOrderInfos);
        $data = $this->model_checkout_umf_order->sendMsg(URL_YZCM . '/api/umpay/payment', $post_param);
        if (!$data) {
            $errorMsg = '[wechat_pay]创建订单失败:接口无响应.';
            $this->model_checkout_umf_order->sendErrorMsg('wechat_pay', $this->customer->getId(), $errorMsg);
            Logger::error($errorMsg, 'error');
            return Json::fail('Sorry, the system is busy now, please try again later.');
        }
        $retMsg = json_decode($data['retMsg'], true);
        if (isset($retMsg['url'])) {
            //成功
            //个人信息存入session
            $this->session->set('wechatInfo',$payData);
            $json = Json::success();
            $payInfo = [
                'qrcodeUrl' => $retMsg['url'],
                'total_yzc' => $order_info['total'],
                'currency_yzc' => $post_param['currency_code'],
                'rate_yzc' => round((double)$post_param['rate_yzc'], 4),
                'total_cny' => $post_param['total'],
            ];
            $json->payInfo = $payInfo;
            return $json;
        } else {
            $this->log->write("[wechat_pay]创建订单失败 order_id:$order_id,customer_id:" . $this->customer->getId()
                . ',retMsg:' . json_encode($data));
            return Json::fail('Identity information is wrong  or system error.');
        }
    }

    public function createPayment($data)
    {
        try {
            $this->load->model('checkout/umf_order');
            $postParam = $this->model_checkout_umf_order->createPostData($data);
            $returnData = $this->model_checkout_umf_order->sendMsg(URL_YZCM . '/api/umpay/payment', $postParam);
            if (!$returnData) {
                $errorMsg = '[wechat_pay]创建订单失败:接口无响应.';
                $this->model_checkout_umf_order->sendErrorMsg('wechat_pay', $this->customer->getId(), $errorMsg);
                Logger::error($errorMsg, 'error');
               throw new PaymentException(static::TIME_OUT);
            }
            $retMsg = json_decode($returnData['retMsg'] ?? '', true);
            if (isset($retMsg['url'])) {
                $payData = $data['pay_data'];
                //成功
                //个人信息存入session
                $this->session->set('wechatInfo', $payData);
                $json = Json::success();
                $payInfo = [
                    'qrcodeUrl' => $retMsg['url'],
                    'total_yzc' => $postParam['total_yzc'],
                    'currency_yzc' => $postParam['currency_code'],
                    'rate_yzc' => round((double)$postParam['rate_yzc'], 4),
                    'total_cny' => $postParam['total'],
                ];
                $json->payInfo = $payInfo;
                return $json;
            } else {
                $errorCode = $retMsg['meta']['ret_code'] ?? '';
               throw new PaymentException($errorCode);
            }
        }catch (Exception $e){
            Logger::error("[wechat_pay]创建订单失败 order_id:,customer_id:" . $this->customer->getId().",errorMsg:{$e->getMessage()}");
            $json = Json::fail($e->getMessage());
            return  $json;
        }
    }
}
