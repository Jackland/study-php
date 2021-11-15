<?php

namespace App\Helper;

use App\Enums\Customer\CustomerAccountingType;
use App\Logging\Logger;
use Symfony\Component\HttpClient\HttpClient;

class GigaOnsiteHelper
{
    /**
     * 请求giga onsite修改销售订单地址接口
     * PS:失败代表:GIGA已经生成发货单, 并且合并了label,已经发货状态了
     * @param string $salesOrderId
     * @param array $updateInfo
     * @return array
     * @throws
     */
    public function updateOrderAddress(string $salesOrderId, array $updateInfo,$runId)
    {
        $returnResult = [
            'code' => 1,
            'msg' => '',
        ];
        $postData = [
            'shipName' => $updateInfo['name'],
            'shipEmail' => $updateInfo['email'],
            'shipPhone' => $updateInfo['phone'],
            'shipAddress1' => $updateInfo['address'],
            'shipCity' => $updateInfo['city'],
            'shipCountryCode' => $updateInfo['country'],
            'shipState' => $updateInfo['state'],
            'shipZipCode' => $updateInfo['code'],
            'orderNumber' => $salesOrderId,
            'customerComments' => $updateInfo['comments'],
            'runId' => $runId,
        ];
        try {
            $client = HttpClient::create();
            $url = GIGA_ONSITE_API_URL . '/onsiteOrder/modify';
            $response = $client->request('POST', $url, [
                'headers' => [
                    'Content-Type: application/json; charset=utf-8',
                ],
                'json' => $postData,
            ]);
            $result = $response->toArray();
            if (!$result['success']) {
                $returnResult['code'] = 0;
                $returnResult['msg'] = $result['msg'];
                Logger::syncCustomer('giga onsite 修改地址[return][' . $salesOrderId . ']:' . $result['msg']);
            }
        } catch (\Exception $e) {
            Logger::syncCustomer('giga onsite 修改地址[exception][' . $salesOrderId . ']:' . $e->getMessage());
            $returnResult['code'] = 0;
            $returnResult['msg'] = 'Failed!';
        }
        return $returnResult;
    }

    /**
     * 请求giga onsite取消订单接口
     * PS:失败代表:GIGA已经生成发货单, 并且合并了label,已经发货状态了
     * @param string $salesOrderId
     * @return array
     * @throws
     */
    public function cancelOrder($salesOrderId = '',$runId = '')
    {
        $returnResult = [
            'code' => 1,
            'msg' => '',
        ];
        $postData = [
            'id' => $salesOrderId,
            'runId' => $runId,
        ];
        try {
            $client = HttpClient::create();
            $url = GIGA_ONSITE_API_URL . '/onsiteOrder/cancel';
            $response = $client->request('POST', $url, [
                'headers' => [
                    'Content-Type: application/json; charset=utf-8',
                ],
                'query' => $postData,
            ]);
            $result = $response->toArray();
            if (!$result['success']) {
                $returnResult['code'] = 0;
                $returnResult['msg'] = $result['msg'];
                Logger::syncCustomer('giga onsite 取消订单[return][' . $salesOrderId . ']:' . $result['msg']);
            }
        } catch (\Exception $e) {
            Logger::syncCustomer('giga onsite 取消订单[exception][' . $salesOrderId . ']:' . $e->getMessage());
            $returnResult['code'] = 0;
            $returnResult['msg'] = 'Failed!';
        }
        return $returnResult;
    }

    /**
     * seller类型从onsite改为其它时候，触发JAVA接口，去重新更新运费=>王发炜
     * @param int $sellerId
     * @param int $fromAccountType
     * @param int $toAccountType
     * @throws
     */
    public static function sendProductsFreightRequest(int $sellerId, int $fromAccountType, int $toAccountType)
    {
        if (!$sellerId || !$fromAccountType || !$toAccountType) {
            return true;
        }

        if ($fromAccountType != CustomerAccountingType::GIGA_ONSIDE || $fromAccountType == $toAccountType) {
            return true;
        }

        $queryData = [
            'sellerId' => $sellerId,
            'typeFrom' => $fromAccountType,
            'typeTo' => $toAccountType,
        ];

        $client = HttpClient::create();

        $url = B2B_MANAGEMENT_BASE_URL . '/api/onsite/type/change';
        $token = B2B_MANAGEMENT_AUTH_TOKEN;
        $response = $client->request('GET', $url, [
            'headers' => [
                'Content-Type: application/json; charset=utf-8',
                "Authorization: Bearer {$token}",
            ],
            'query' => $queryData,
        ]);

        $result = $response->toArray();
        if ($result['code'] != 200) {
            Logger::onsiteSellerTypeChange("onsite类型seller，改为{$toAccountType}类型seller时，请求JAVA接口失败，失败原因:" . $result['msg']);
            return true;
        }

        return true;
    }

}
