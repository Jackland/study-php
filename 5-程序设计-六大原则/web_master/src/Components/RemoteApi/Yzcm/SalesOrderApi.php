<?php

namespace App\Components\RemoteApi\Yzcm;

use App\Components\RemoteApi\Exceptions\ApiResponseException;

class SalesOrderApi extends BaseYzcmApi
{
    /**
     * 通知 yzcm 将自提货的 BOL 文件发给 OMD
     * @param int $salesOrderId
     * @return bool
     */
    public function sendPickUpBolToOmd(int $salesOrderId): bool
    {
        try {
            $this->api("/buyerPickUp/sendBolToOmd/{$salesOrderId}");
        } catch (ApiResponseException $e) {
            return false;
        }
        return true;
    }
}
