<?php

namespace App\Services\Seller;

use App\Logging\Logger;
use App\Models\Seller\SellerClientCustomerMap;
use App\Models\Seller\SellerAccountApply;
use Symfony\Component\HttpClient\HttpClient;
use Exception;

class SellerService
{
    /**
     * 同步更新信息到seller开户表(java那块在使用)
     * @param int $customerId
     * @param array $updateData
     * @return bool|int
     */
    public function updateSellerAccountApplyInfo($customerId, $updateData = [])
    {
        if (!$customerId || !$updateData) {
            return true;
        }
        $accountApplyInfo = SellerClientCustomerMap::query()
            ->alias('a')
            ->leftJoin('tb_seller_account_apply as b', 'a.apply_id', '=', 'b.id')
            ->where('a.seller_id', $customerId)
            ->selectRaw('a.*,b.country_id,b.email,b.password,b.account_type,b.store_name')
            ->first();
        if ($accountApplyInfo && $accountApplyInfo->apply_id) {
            return SellerAccountApply::query()->where('id', $accountApplyInfo->apply_id)->update($updateData);
        }
        return true;
    }

    /**
     * 触发b2b manage同步数据到财务数据平台
     *
     * @param int $sellerId
     * @return bool
     */
    public function sendApply($sellerId)
    {
        if (!$sellerId) {
            return false;
        }
        $url = B2B_MANAGEMENT_BASE_URL . '/seller/sendApply';
        $sellerClientCustomerMap = SellerClientCustomerMap::query()
            ->where('seller_id', $sellerId)
            ->first(['apply_id', 'seller_client_id']);
        if (!$sellerClientCustomerMap) {
            return false;
        }
        //tb_b2b_sys_manage_api
        $params = [
            'applyId' => $sellerClientCustomerMap->apply_id,
            'editFlag' => true,
            'sellerClientId' => $sellerClientCustomerMap->seller_client_id,
            'apiOperator' => "php-seller",
        ];
        // 发送请求
        $client = HttpClient::create();
        $token = B2B_MANAGEMENT_AUTH_TOKEN;
        $url .= "?" . http_build_query($params);
        try {
            $response = $client->request('POST', $url, [
                'headers' => [
                    "Authorization: Bearer {$token}",
                ],
                'json' => $params,
            ]);
            $data = $response->toArray();
        } catch (Exception $exception) {
            Logger::sellerOperating("[{$sellerId}]修改登入邮箱请求b2b manage异常", 'error', [
                Logger::CONTEXT_VAR_DUMPER => [
                    'url' => $url,
                    'params' => $params,
                    'msg' => $exception->getMessage()
                ]
            ]);
            return false;
        }
        if (!isset($data['code']) || $data['code'] !== 200) {
            // 失败，记录日志
            Logger::sellerOperating("[{$sellerId}]修改登入邮箱请求b2b manage失败", 'error', [
                Logger::CONTEXT_VAR_DUMPER => [
                    'url' => $url,
                    'params' => $params,
                    'response' => $data
                ]
            ]);
            return false;
        }
        return true;
    }
}
