<?php

namespace App\Services\SalesOrder\CancelOrder;

use App\Logging\Logger;
use App\Models\SalesOrder\JoyBuyOrderInfo;
use Exception;
use Symfony\Component\HttpClient\HttpClient;

class DropshipCancelOrderService extends CommonCancelOrderService
{
    /**
     * @param int $orderId
     * @return array
     */
    public function cancelJoyBuyOrder(int $orderId):array
    {
        $returnResult = [
            'code' => 1,
            'msg' => '',
        ];
        try {
            $postData = [
                'grant_type'=>'client_credentials',
            ];
            $url = JOY_BUY_API_URL .'/api-auth-v1/oauth/token';
            $client = HttpClient::create();
            $string = JOY_BUY_USERNAME .':'.JOY_BUY_PASSWORD;
            $string = base64_encode($string);
            $response = $client->request('POST', $url, [
                'headers' => [
                    'Authorization:Basic '.$string,
                ],
                'query' => $postData,
            ]);

            $result = $response->toArray();
            if(isset($result['access_token'])){
                $token = $result['access_token'];
            }else{
                Logger::salesOrder($result,'error');
                throw new Exception('joy buy 订单token获取失败');
            }
            // 获取token后
            $cancelData = [
                'appKey'=> JOY_BUY_APP_KEY,
                'jdOrderId'=> $this->getJoyBuyOrderId($orderId),
            ];
            $cancelUrl = JOY_BUY_API_URL .'/amazon-b2b-v1/joybuy/api/platform/order/cancel';
            $cancelResponse = $client->request('POST', $cancelUrl, [
                'headers' => [
                    'Authorization:Bearer '.$token,
                    'Content-Type: application/json; charset=utf-8',
                ],
                'json' => $cancelData,

            ]);
            $cancelResult = $cancelResponse->toArray();
            Logger::salesOrder($cancelResult);
            if(isset($cancelResult['success'])){
                if (!$cancelResult['success']) {
                    $returnResult['code'] = 0;
                    $returnResult['msg'] = $cancelResult['msg'];
                    Logger::salesOrder('joy buy 取消订单[return][' . $orderId . ']:' . $cancelResult['msg'],'error');
                }
            }else{
                Logger::salesOrder($result,'error');
                throw new Exception('joy buy 订单取消异常。');
            }

        } catch (\Exception $e) {
            Logger::salesOrder('joy buy 取消订单[exception][' . $orderId . ']:' . $e->getMessage(),'error');
            $returnResult['code'] = 0;
            $returnResult['msg'] = 'Failed!';
        }
        return $returnResult;
    }

    /**
     * 根据销售单查询对应的joy buyer 订单
     * @param int $orderId
     * @return mixed
     *
     */
    private function getJoyBuyOrderId(int $orderId)
    {
        return JoyBuyOrderInfo::where('sales_order_id',$orderId)->value('joy_buy_order_id');
    }
}
