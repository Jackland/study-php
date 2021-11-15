<?php

namespace App\Helper;

use App\Logging\Logger;
use Symfony\Component\HttpClient\HttpClient;
use App\Repositories\Customer\CustomerRepository;

class CustomerHelper
{
    /**
     * 同步seller(供应商)信息给giga onsite
     * 此接口初版是同步单个seller信息，后面调整成全量同步到giga onsite，所以传参$customerId并无实际意义，目的是兼容不改其它地方代码，后面这个会移动到java
     * @param int $customerId
     * @return bool
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    public function postAccountInfoToOnSite($customerId): bool
    {
        if (empty($customerId)) {
            Logger::syncCustomer('Failed!! seller_id is empty');
            return false;
        }
        $sellerList = app(CustomerRepository::class)->getGigaOnsiteSellerList();
        $postData = [];
        foreach ($sellerList as $seller) {
            $postData[] = [
                'sellerId' => $seller->user_number,
                'supplierName' => $seller->screenname,
                'supplierAccount' => $seller->email,
                'agentOperation' => $seller->agent_operation,
            ];
        }
        Logger::syncCustomer($postData);
        if ($postData) {
            try {
                $client = HttpClient::create();
                $url = GIGA_ONSITE_API_URL . '/supplier/syncSupplier';
                $response = $client->request('POST', $url, [
                    'headers' => [
                        'Content-Type: application/json; charset=utf-8',
                    ],
                    'json' => $postData,
                ]);
                $result = $response->toArray();
                if (!$result['success']) {
                    Logger::syncCustomer('giga onsite 同步seller信息失败[api]:' . $result['msg']);
                    return false;
                }
            } catch (\Exception $e) {
                Logger::syncCustomer('giga onsite 同步seller信息失败[error]:' . $e->getMessage());
                return false;
            }
        }

        return true;
    }
}
