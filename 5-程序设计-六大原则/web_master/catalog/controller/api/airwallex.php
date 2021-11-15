<?php

use App\Logging\Logger;
use App\Repositories\Pay\AirwallexRepository;
use App\Services\Pay\AirwallesService;

class ControllerApiAirwallex extends Controller
{
    public function paymentCallback()
    {
        //获取body里的参数
        $bodyData = @file_get_contents('php://input');
        $this->log->write('[callbackByYzcm]回调转发 ' . $bodyData);
        $this->load->model('checkout/order');
        $timestamp = $_SERVER['HTTP_X_TIMESTAMP'];
        $signature = $_SERVER['HTTP_X_SIGNATURE'];
        $header = array("x-timestamp:$timestamp", "x-signature:$signature", "Content-type:application/json;charset='utf-8'", "Accept:application/json");
        $resData = post_url(URL_YZCM . '/api/airwallex/webhook/rechargeReceive', $bodyData, $header);
        $result = array(
            'success' => true,
            'msg' => $resData
        );
        // Do something with event
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($result));
    }

    // 空中云汇账号绑定回调
    public function accountBindCallback()
    {
        $timestamp = $this->request->header('x-timestamp', '');
        $signature = $this->request->header('x-signature', '');
        $bodyContent = $this->request->bodyBag()->raw();

        $checkSign = $this->checkSign($timestamp, $bodyContent, $signature);
        if (! $checkSign) {
            return $this->jsonFailed('Failed to verify the signature.')->setStatusCode(400);
        }

        $bodyArr = json_decode($bodyContent, true);
        if (empty($bodyArr['name']) || strtolower($bodyArr['name']) != 'account.active') {
            Logger::airwallex('云汇绑定|通知类型不正确');
            return $this->jsonFailed('Invalid Status.')->setStatusCode(400);
        }
        if (empty($bodyArr['data']['status']) || strtoupper($bodyArr['data']['status']) != 'ACTIVE') {
            Logger::airwallex('云汇绑定|通知状态不正确');
            return $this->jsonFailed('Invalid Status.')->setStatusCode(400);
        }
        if (empty($bodyArr['data']['identifier']) || empty($bodyArr['data']['id'])) {
            Logger::airwallex('云汇绑定|通知数据不正确');
            return $this->jsonFailed('Invalid Status.')->setStatusCode(400);
        }

        $airwallexService = app(AirwallesService::class);
        $update = $airwallexService->updateAirwallexId($bodyArr['data']['identifier'], $bodyArr['data']['id']);

        if (! $update) {
            return $this->jsonFailed('Invalid Status.')->setStatusCode(400);
        }

        return $this->jsonSuccess();
    }

    /**
     * 回调验证签名
     *
     * @param string $timestamp 请求头部时间戳
     * @param string $content 请求内容体
     * @param string $signature 请求头部签名
     * @return bool
     */
    private function checkSign($timestamp, $content, $signature)
    {
        Logger::airwallex(['x-timestamp:' => $timestamp, 'x-signature:' => $signature, 'content:' => $content]);

        $airwallexRepo = app(AirwallexRepository::class);
        $secret = $airwallexRepo->getCallbackSecret();

        $sign = hash_hmac('sha256', $timestamp . $content, $secret);
        if ($sign != $signature) {
            Logger::airwallex("签名错误:sign:$sign;signature:$signature");
            return false;
        }

        return true;
    }
}